# Software Requirements Specification (SRS)


# AI Agent Implementation Rules

This document is intended to be implemented by an AI coding agent.

The agent must implement Version 1 as a working application, not only generate a plan.

## Important Product Decisions

- Do not include task management.
- Do not include a separate client module.
- Each project stores customer/company information directly.
- The project is the main entity.
- Financial tracking is the first priority.
- Keep the system simple and usable for a small software development company.

## Agent Behavior

If a requirement is unclear:
- choose the simplest practical solution
- do not ask the user unless absolutely required
- continue implementation
- document assumptions in README.md

## Implementation Priority

1. Database schema
2. PHP backend API
3. React layout
4. Project CRUD
5. Payments
6. Recurring fees
7. Expenses
8. Dashboard
9. Invoice/receipt
10. README and testing notes

## Definition of Done

The app is done when:
- user can create/edit/delete projects
- user can record payments
- system calculates paid amount and remaining balance
- user can record server/domain/hosting fees
- dashboard shows financial summary
- frontend connects to backend API
- database schema is included
- README explains how to run locally

# Project & Financial Management Website

**Target Stack:** React Frontend + PHP Backend + MySQL Database  
**Project Type:** Internal management system for a small software development company  
**Version:** 1.4  
**Prepared For:** AI Coding Agent Implementation  

---

## 1. Introduction

### 1.1 Purpose

This document defines the complete Software Requirements Specification for a responsive web-based Project and Financial Management System.

The system is designed for a software development company to manage projects, project payments, upfront payments, remaining balances, recurring server/domain/hosting fees, project expenses, invoices, receipts, and financial reminders.

This version does **not** include task management and does **not** include a separate client module. Customer/client details are stored directly inside each project record.

The SRS is written in detailed implementation-ready format so an AI coding agent can build the application using React, PHP, and MySQL.

---

## 2. System Scope

### 2.1 Included in Version 1

The system must include:

1. Authentication
2. Dashboard
3. Project Management
4. Project Financial Tracking
5. Payment Records
6. Recurring Fee Tracking
7. Project Expense Tracking
8. Invoice Management
9. Receipt Management
10. Reminder Center
11. Reports
12. User Management
13. Responsive Layout

### 2.2 Excluded from Version 1

The system must not include:

1. Task management
2. Kanban board
3. Separate client/customer module
4. Chat or messaging
5. Mobile app
6. Payroll
7. Advanced accounting ledger
8. Inventory
9. Multi-company support

---

## 3. Main Business Concept

The project is the center of the system.

Each project stores:

- Project information
- Customer/company information as text fields
- Contract amount
- Upfront payment requirement
- Payments received
- Remaining balance
- Server/domain/hosting fees
- Maintenance fees
- Expenses
- Invoices
- Receipts

Example:

A project has a total contract amount of 2,000,000 MMK.

The customer paid 500,000 MMK upfront.

Later the customer paid 700,000 MMK.

The system must automatically calculate:

- Total contract value: 2,000,000 MMK
- Total paid: 1,200,000 MMK
- Remaining balance: 800,000 MMK
- Payment status: Partially Paid

---

## 4. User Roles

### 4.1 Admin

Admin can access all modules.

Permissions:

- Create, update, delete projects
- Create, update, delete payments
- Create, update, delete expenses
- Create, update, delete recurring fees
- Generate invoice softcopies and manage internal receipt records
- View dashboard and reports
- Manage users
- Change system settings

### 4.2 Staff

Staff can manage daily data but cannot manage system users.

Permissions:

- View projects
- Create and update projects
- Add payments
- Add expenses
- Add recurring fee records
- Generate invoice softcopies and manage internal receipt records
- View reports

### 4.3 Viewer

Viewer can only read data.

Permissions:

- View dashboard
- View projects
- View payments
- View reports
- Cannot create, update, or delete records

---

## 5. Technology Requirements

### 5.1 Frontend

Use React.

Recommended libraries:

- React Router for routing
- Axios or Fetch API for API calls
- React Hook Form for forms
- Yup or Zod for validation
- Recharts or Chart.js for dashboard charts
- Tailwind CSS or plain CSS modules for responsive UI
- Lucide React or React Icons for icons

### 5.2 Backend

Use PHP.

Acceptable backend styles:

- Plain PHP REST API, or
- PHP MVC structure, or
- Laravel if preferred

For simple implementation, plain PHP REST API is acceptable.

### 5.3 Database

Use MySQL.

### 5.4 Authentication

Use PHP session authentication or JWT authentication.

Recommended for API style:

- JWT access token
- Password hashing using `password_hash()`
- Password verification using `password_verify()`

---

## 6. System Architecture

### 6.1 Frontend Structure

Recommended React folder structure:

```text
src/
  api/
    apiClient.js
    authApi.js
    projectApi.js
    paymentApi.js
    expenseApi.js
    recurringFeeApi.js
    invoiceApi.js
    receiptApi.js
    reportApi.js
  components/
    layout/
      AppLayout.jsx
      Sidebar.jsx
      Topbar.jsx
      MobileNav.jsx
    common/
      Button.jsx
      Card.jsx
      Modal.jsx
      Table.jsx
      Badge.jsx
      Input.jsx
      Select.jsx
      DateInput.jsx
      ConfirmDialog.jsx
      EmptyState.jsx
      LoadingSpinner.jsx
    project/
      ProjectForm.jsx
      ProjectFinancialSummary.jsx
      ProjectStatusBadge.jsx
    finance/
      PaymentForm.jsx
      ExpenseForm.jsx
      RecurringFeeForm.jsx
      InvoiceForm.jsx
      ReceiptForm.jsx
  pages/
    LoginPage.jsx
    DashboardPage.jsx
    ProjectsPage.jsx
    ProjectDetailPage.jsx
    ProjectCreatePage.jsx
    ProjectEditPage.jsx
    PaymentsPage.jsx
    ExpensesPage.jsx
    RecurringFeesPage.jsx
    InvoicesPage.jsx
    ReceiptsPage.jsx
    RemindersPage.jsx
    ReportsPage.jsx
    UsersPage.jsx
    SettingsPage.jsx
  routes/
    AppRoutes.jsx
    ProtectedRoute.jsx
  utils/
    formatCurrency.js
    formatDate.js
    calculations.js
    constants.js
  App.jsx
  main.jsx
```

### 6.2 Backend Structure

Recommended PHP folder structure:

```text
backend/
  config/
    database.php
    cors.php
  helpers/
    response.php
    auth.php
    validation.php
  middleware/
    authMiddleware.php
  controllers/
    AuthController.php
    ProjectController.php
    PaymentController.php
    ExpenseController.php
    RecurringFeeController.php
    InvoiceController.php
    ReceiptController.php
    ReportController.php
    ReminderController.php
    UserController.php
  models/
    User.php
    Project.php
    Payment.php
    Expense.php
    RecurringFee.php
    Invoice.php
    InvoiceItem.php
    Receipt.php
  routes/
    api.php
  public/
    index.php
  uploads/
```

---

## 7. Core Modules

---

## 7.1 Authentication Module

### 7.1.1 Login

The user must login with:

- Email
- Password

Successful login returns:

- User ID
- Name
- Email
- Role
- Token/session

### 7.1.2 Logout

The system must allow users to logout.

### 7.1.3 Password Security

Passwords must be hashed.

Never store plain text passwords.

---

## 7.2 Dashboard Module

### 7.2.1 Dashboard Cards

The dashboard must show these cards:

1. Total Projects
2. Active Projects
3. Completed Projects
4. Total Contract Value
5. Total Received Amount
6. Total Outstanding Amount
7. This Month Income
8. This Month Expenses
9. Net Profit
10. Overdue Payments
11. Server Fees Due This Month
12. Upcoming Renewals

### 7.2.2 Dashboard Charts

Include these charts:

1. Monthly income chart
2. Monthly expense chart
3. Project payment status pie chart
4. Recurring fee due chart

### 7.2.3 Dashboard Lists

Show these short lists:

1. Recent payments
2. Recently created projects
3. Overdue project balances
4. Upcoming server/domain/hosting renewals

---

## 7.3 Project Management Module

### 7.3.1 Project List Page

The project list must show:

- Project code
- Project name
- Customer/company name
- Contact phone
- Project status
- Contract amount
- Paid amount
- Remaining balance
- Payment status
- Delivery date
- Actions

Actions:

- View
- Edit
- Delete

Filters:

- Search by project name
- Search by customer/company name
- Filter by project status
- Filter by payment status
- Filter by date range

### 7.3.2 Project Fields

Each project must include:

#### Basic Information

- Project code
- Project name
- Project type
- Description
- Project status
- Priority
- Start date
- Delivery date
- Completion date

#### Customer Information

Do not create a separate client table in Version 1.

Store these fields directly in the project table:

- Customer/company name
- Contact person
- Contact phone
- Contact email
- Address

The contact person is the project owner/customer contact. These owner details must appear in project details, reports, and project-linked invoices.

#### Financial Information

- Contract amount
- Upfront required amount
- Payment due date
- Currency
- Discount amount
- Tax amount
- Notes

#### Technical Information

- Domain name
- Domain purchase date
- Domain reminder date, defaulting to exactly one year after the purchase date
- Actual domain/server payment date
- Domain/server price
- Hosting provider
- Server provider
- Server IP
- Git repository URL
- Admin panel URL
- Production URL
- Technical notes

### 7.3.3 Project Status Values

Use these project statuses:

- New
- In Progress
- Waiting Payment
- Delivered
- Completed
- Cancelled
- On Hold

### 7.3.4 Payment Status Values

Payment status must be calculated automatically.

Values:

- Unpaid
- Partially Paid
- Fully Paid
- Overdue

Rules:

- If total paid = 0, status is Unpaid.
- If total paid > 0 and total paid < total payable, status is Partially Paid.
- If total paid >= total payable, status is Fully Paid.
- If remaining balance > 0 and payment due date is before today, status is Overdue.

### 7.3.5 Project Detail Page

Project detail must include tabs:

1. Overview
2. Payments
3. Recurring Fees
4. Expenses
5. Invoices
6. Receipts
7. Notes

#### Overview Tab

Show:

- Project information
- Customer information
- Technical information
- Financial summary

Financial summary must show:

- Contract amount
- Discount
- Tax
- Total payable
- Total paid
- Remaining balance
- Payment percentage
- Payment status

---

## 7.4 Payment Module

### 7.4.1 Purpose

Payments record money received from a project.

Payments are directly linked to a project.

### 7.4.2 Payment Fields

Each payment must include:

- Payment ID
- Project ID
- Payment date
- Amount
- Financial account ID (the person/account that received the money)
- Recorded by user ID (the authenticated user who entered it)
- Notes
- Created at
- Updated at

The payment form must keep Project, Payment Date, Amount, Payment Stage, Historical Payment, and Received By as its main fields. Payment Stage uses Upfront, Progress Payment, Final Payment, or Other. Payment method is not a user-facing Version 1 requirement and may retain an internal default for compatibility.

### 7.4.3 Received By

For a current payment, the user must choose one active financial account. Financial accounts use user-defined names, such as a person, partner, card, wallet, or bank name. No names are fixed or pre-seeded. Account type and payment method are not required.

For an old payment received before KSSPM financial tracking began, the user may mark the payment Historical. A historical payment requires its original date and amount but no financial account. It counts toward project/domain paid totals, revenue, profit, reports, and automatic payment status, but it must not create a User Financial Receive movement or change any account balance.

### 7.4.4 Automatic Financial Recording

Creating a non-historical project payment must create one linked User Financial Receive transaction in the same database transaction. Updating the payment must update the linked Receive transaction. Deleting the payment must delete the linked Receive transaction. Converting a payment to Historical must remove its linked Receive; converting it back requires an account and recreates the Receive.

The user must not enter this Receive transaction a second time. Linked Receive transactions are read-only in User Financial and are managed from the project payment.

### 7.4.5 Payment List Page

Show:

- Payment date
- Project code
- Project name
- Customer/company name
- Amount
- Received by financial account
- Recorded by user
- Actions

Filters:

- Date range
- Project
- Received by financial account

### 7.4.6 Payment Calculation

When a payment is created, updated, or deleted, the project financial summary must update automatically.

Formula:

```text
Total Payable = Contract Amount - Discount Amount + Tax Amount
Total Paid = SUM(payments.amount) for the project
Remaining Balance = Total Payable - Total Paid
Payment Percentage = Total Paid / Total Payable * 100
```

### 7.4.7 User Financial Tracking

User Financial tracks balances held by user-defined named financial accounts.

Admins must be able to create, view, update, activate/deactivate, and delete financial accounts. Each account includes a unique name, opening balance, and status. An account with payment or transaction history cannot be deleted; it must be set to Inactive to preserve history.

Supported movements:

- Receive: generated automatically from a project payment only
- Use: manually records money used from one account
- Transfer: manually moves a balance from one account to another

For Use, require date, Used By, amount, and an optional note. For Transfer, require date, From, To, amount, and an optional note. From and To must be different. Negative balances are permitted because Version 1 may begin with incomplete historical balances.

```text
Account Balance = Opening Balance + Receives + Transfers In - Uses - Transfers Out
Combined Balance = SUM(Account Balances)
```

Transfers do not change combined balance and must not count as project income or business expense. The screen must show balance cards, transaction history, search, movement/account/date filters, Today/Week/Month/Lifetime periods, responsive tables, and create/edit/delete actions for manual Use and Transfer records.

---

## 7.5 Recurring Fee Module

### 7.5.1 Purpose

Recurring fees track server, hosting, domain, SSL, maintenance, and other renewable services.

Recurring fees are linked to a project.

The screen is a unified renewal view. Each priced annual Domain Billing period appears automatically as one read-only Domain Billing row with fee type `Server`. Its customer price is the combined domain and hosting/server charge. The row remains owned by Domain Billing, is derived for display, and must not be inserted into `recurring_fees`, create duplicate reminders, or be counted twice in reports.

Saving or editing a project must not generate recurring-fee rows. Domain Billing is the only automatic source shown in the Recurring Fees view. Existing Project Generated hosting/server rows must be removed; manual recurring fees remain available for exceptional services managed directly by the user.

### 7.5.2 Recurring Fee Fields

Each recurring fee must include:

- Fee ID
- Project ID
- Fee name
- Fee type
- Amount
- Billing cycle
- Last paid date
- Next due date
- Reminder days before due
- Status
- Auto create reminder
- Notes
- Source type (`Manual`; derived rows use `Domain Billing`)
- Source key for a project-generated schedule
- Created at
- Updated at

When Auto create reminder is disabled, the fee remains part of financial and recurring-fee reports but must not appear on the Reminders screen or in dashboard upcoming renewals.

The Recurring Fees list must show and filter Source. Domain Billing rows allow detail viewing only; edit, delete, payment, purchase, and reversal actions remain in Domain Billing. Manual rows use the normal recurring-fee actions.

Zero-priced domain quotes do not appear as fees. After a successor annual domain period is created, the preceding fully paid period remains Paid and must not generate another overdue state in the unified list.

### 7.5.3 Fee Types

Use these values:

- Domain
- Hosting
- VPS
- Server
- SSL
- Maintenance
- API Subscription
- Other

### 7.5.4 Billing Cycles

Use these values:

- Monthly
- Quarterly
- Half Yearly
- Yearly
- One Time

### 7.5.5 Recurring Fee Status

Use these values:

- Not Due
- Due Soon
- Due Today
- Overdue
- Paid
- Cancelled

Rules:

- If next due date is greater than today + reminder days, status is Not Due.
- If next due date is within reminder days, status is Due Soon.
- If next due date equals today, status is Due Today.
- If next due date is before today and not paid, status is Overdue.
- If paid, status is Paid.

### 7.5.6 Mark Fee as Paid

When a recurring fee is paid:

1. Create a payment record if the customer paid the fee.
2. Update last paid date.
3. Calculate next due date based on billing cycle.
4. Change status to Not Due.

Example:

If billing cycle is Yearly and next due date is 2026-07-01, after payment the next due date becomes 2027-07-01.

### 7.5.7 Annual Domain Billing

Domain billing is a project-linked annual ledger separate from the project contract and general recurring fees. Each annual period stores the customer quote and registrar purchase independently so a price can be agreed before the domain is bought.

Required fields:

- Project, optional domain name, period label, quote date, customer domain price, customer due date, reminder lead time, and notes
- Purchase status: Quoted, Purchased, or Cancelled
- Purchase date, annual customer renewal date, actual registrar expiry date, registrar reminder date, registrar/provider, registrar reference, actual registrar cost, historical-purchase flag, and conditional Paid From financial account
- Created-by user and timestamps

Rules:

1. Create Project may optionally include an Initial Server Billing (Domain + Hosting) quote. The project and first quoted Domain Billing period must be created in one database transaction; validation failure must create neither record.
2. The create-only initial server fields are domain name (optional), period label, quote date, combined customer server price, customer due date, reminder lead time, and notes. Registrar purchase fields are not collected during project creation.
3. Editing a project must never create or duplicate a Domain Billing period. Existing periods are managed from Domain Billing.
4. A quoted period may exist with no domain name, purchase date, registrar cost, or financial transaction.
5. Customer payments may be recorded before or after purchase, may be partial, and must never exceed that period's customer price.
6. Every current customer domain payment requires a Received By financial account and creates exactly one linked Receive movement. A historical domain payment requires no account and creates no movement while still settling the annual customer price.
7. Domain payments count as business income but do not reduce the project's contract balance. Payments must carry a scope of Project, Domain, or Recurring.
8. A current registrar purchase requires purchase date, positive actual cost, and a Paid From financial account. A purchase made before KSSPM financial tracking may be marked historical and requires no account.
9. Saving any purchase atomically creates or updates exactly one linked Domain Purchase project expense. A current purchase also creates one linked User Financial Use movement and deducts the cost from the chosen account once. A historical purchase creates no Use movement and changes no account balance.
10. The linked registrar expense and Use movement are read-only outside Domain Billing. Reversing the purchase removes both and restores the account balance.
11. Customer renewal defaults to one calendar year after purchase and drives the next annual customer quote and charge reminder.
12. Registrar expiry defaults to one calendar year after purchase but may be set several years later when a multi-year registration was bought. The registrar reminder uses the configured lead time before that actual expiry date.
13. All calculated annual dates use the last valid date for leap-day purchases.
14. Renew creates the next customer period from the annual customer renewal date, not the registrar expiry date, and preserves all prior-year prices, payments, costs, and dates.
15. If registrar coverage remains active, the next customer period carries that coverage forward with zero new registrar cost and no expense or account movement. When coverage has ended, the next period remains quoted until the actual registrar extension is recorded.
16. Payment state is Not Priced, Unpaid, Partially Paid, or Paid. Effective purchase state is Not Purchased, Active, Expired, or Cancelled.

Domain profit is reported separately:

```text
Realized Domain Profit = Customer Domain Payments - Actual Registrar Cost
Expected Domain Profit = Customer Domain Price - Actual Registrar Cost
```

The Domain Billing screen must support project, payment-state, purchase-state, date, and text filters; annual detail; quote editing; customer payment; registrar purchase/correction/reversal; and next-period renewal. It must work on phone, tablet, and desktop.

---

## 7.6 Expense Module

### 7.6.1 Purpose

Expenses track the company cost for each project.

### 7.6.2 Expense Fields

Each expense must include:

- Expense ID
- Project ID
- Expense date
- Expense category
- Amount
- Paid to
- Payment method
- Reference number
- Notes
- Created by user ID
- Created at
- Updated at

### 7.6.3 Expense Categories

Use these values:

- Domain Purchase
- Hosting Purchase
- VPS Purchase
- Server Cost
- SSL Cost
- API Cost
- SMS Cost
- Developer Cost
- Design Cost
- Transport
- Other

### 7.6.4 Expense List Page

Show:

- Expense date
- Project
- Category
- Amount
- Paid to
- Payment method
- Created by
- Actions

---

## 7.7 Invoice Module

### 7.7.1 Purpose

Invoices are created for project payment requests, server fees, maintenance fees, or other charges.

### 7.7.2 Invoice Fields

Each invoice must include:

- Invoice ID
- Invoice number
- Project ID
- Invoice date
- Due date
- Invoice type
- Subtotal
- Discount amount
- Tax amount
- Total amount
- Paid amount
- Balance amount
- Status
- Notes
- Created by user ID
- Created at
- Updated at

### 7.7.3 Invoice Item Fields

Each invoice can have many invoice items.

Each item includes:

- Item ID
- Invoice ID
- Description
- Quantity
- Unit price
- Total price

### 7.7.4 Invoice Types

Use these values:

- Project Invoice
- Upfront Invoice
- Progress Invoice
- Final Invoice
- Hosting Invoice
- Domain Invoice
- Maintenance Invoice
- Other

### 7.7.5 Invoice Status

Use these values:

- Draft
- Sent
- Partially Paid
- Paid
- Overdue
- Cancelled

### 7.7.6 Invoice Softcopy Layout

The system must generate an A4-formatted invoice softcopy. Users save the invoice as PDF through the browser PDF dialog. Receipts have no printable or downloadable document output in Version 1.7.

Invoice create/edit and saved-invoice preview must use a full-screen workspace so the complete A4 page can be inspected clearly. Application controls must not appear in the saved PDF.

Invoice must show:

- Company name
- Company logo placeholder
- Invoice number
- Invoice date
- Due date
- Project name
- Customer/company name
- Contact phone
- Item table
- Subtotal
- Discount
- Tax
- Total
- Paid amount
- Balance
- Notes

### 7.7.7 Project-Linked A4 Generator and Numbering

- The invoice editor and preview must follow the visual hierarchy, orange accent, payment callout, item table, totals, payment methods, and contact footer of `invoice-generator-a4-fixed.html`.
- Every invoice must be linked to one project before it can be saved.
- Choosing a project must fill the customer/company name, owner contact, project note, currency, and sensible initial line-item information.
- Invoice numbers are read-only in the frontend and generated by the backend.
- The format is `{invoice_prefix}-YYYY-NNNN`, for example `INV-2026-0001`.
- `YYYY` comes from the invoice issue date. Each year has an independent locked sequence beginning at `0001`; for example, after the final 2026 invoice, the first 2027 invoice is `INV-2027-0001`.
- The numeric portion increases atomically and resets for each calendar year.
- Saved invoices can be reopened, previewed, and saved as PDF from the browser PDF dialog.
- The preview always represents one physical A4 page and scales as a unit on narrow screens.

### 7.7.8 Customizable Invoice Template

Settings must include a full-screen invoice template designer. The saved template is shared by live invoice editing, saved previews, and PDF softcopies.

The designer must provide selectable components for the header, customer/invoice metadata, payment callout, item table, totals, notes, and payment/contact footer. Admins can show or hide components, drag them on the A4 canvas, and configure X/Y position, width, minimum height, padding, margin, text alignment, font family, font size, font weight, line height, text color, and background color.

Page controls must include the base font family, font size, line height, text color, page background, and accent color. Table controls must include header/background colors, border color and width, cell padding, font size, striped rows, and column widths. The editor must support reset-to-default and persistent save actions.

Template data is stored as validated JSON in the `invoice_design` setting. Malformed or oversized template data must be rejected by the API. Existing installations without this setting use the built-in default A4 template.

---

## 7.8 Receipt Module

### 7.8.1 Purpose

Receipt records store payment acknowledgement details after payment is received. They are internal records only and do not generate printable or downloadable receipt documents.

### 7.8.2 Receipt Fields

Each receipt must include:

- Receipt ID
- Receipt number
- Project ID
- Payment ID
- Receipt date
- Amount
- Payment method
- Received from
- Received by user ID
- Notes
- Created at
- Updated at

### 7.8.3 Receipt Output

- Users can create, view, update, and delete receipt records according to their role.
- The system must not show a Print, Save PDF, Download, or document-generation action for receipts.
- Invoice softcopy is the only generated financial document in Version 1.7.

---

## 7.9 Reminder Center

### 7.9.1 Purpose

The reminder center shows important financial reminders.

### 7.9.2 Reminder Types

The system must show reminders for:

- Project payment due
- Project payment overdue
- Domain renewal due
- Hosting renewal due
- Server renewal due
- SSL renewal due
- Maintenance payment due
- Invoice overdue

### 7.9.3 Reminder Page

Show reminders in groups:

1. Due Today
2. Due This Week
3. Overdue
4. Upcoming Renewals

Each reminder shows:

- Reminder type
- Project name
- Customer/company name
- Amount
- Due date
- Status
- Action button

Action buttons:

- View project
- Add payment
- Mark as resolved

---

## 7.10 Reports Module

### 7.10.1 Required Reports

Include these reports:

1. Project financial report
2. Payment collection report
3. Outstanding balance report
4. Expense report
5. Profit report
6. Recurring fee report
7. Invoice report
8. Monthly income and expense report
9. Domain billing report

### 7.10.2 Report Filters

Reports must support filters:

- Date range
- Project status
- Payment status
- Project
- Fee type
- Expense category
- Domain payment status
- Domain purchase status

### 7.10.3 Export

Reports should support:

- Print
- Export CSV

PDF export is optional for Version 1.

### 7.10.4 Financial Collection Overview

The Reports screen must provide these period controls:

- Today: payments received today; project balances and renewal fees due on or before today.
- This Week: payments received and amounts due from Monday through Sunday of the current week.
- This Month: payments received and amounts due in the current calendar month.
- Lifetime: all payments received and all currently outstanding project/renewal amounts.

The summary must show separately:

- Amount received (`payments.amount`)
- Project amount still to collect (remaining project balances)
- Domain amount still to collect (annual customer domain balances)
- Renewal fees due (unpaid recurring fees)
- Total to collect (project balance plus domain balance plus renewal fees)
- Quoted domain/server price total
- Actual registrar cost total

Filters:

- Project
- Fee type, including Domain and Server
- Period

The result must include matching payment rows, outstanding project rows, annual domain billing rows, and recurring-fee rows. Domain revenue, registrar cost, and contract balances must remain distinguishable and must not be double counted.

---

## 7.11 User Management Module

### 7.11.1 User Fields

Each user includes:

- User ID
- Name
- Email
- Password hash
- Role
- Status
- Created at
- Updated at

### 7.11.2 User Status

Use these values:

- Active
- Inactive

---

## 8. Database Design

Use MySQL.

---

## 8.1 Tables Overview

Required tables:

1. users
2. projects
3. financial_accounts
4. domain_billing_periods
5. payments
6. financial_transactions
7. recurring_fees
8. expenses
9. invoices
10. invoice_items
11. receipts
12. invoice_sequences
13. receipt_sequences
14. resolved_reminders
15. settings
16. activity_logs

---

## 8.2 users Table

```sql
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
```

---

## 8.3 projects Table

```sql
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
```

---

## 8.4 domain_billing_periods Table

Stores one immutable-history annual quote/purchase period per project. It includes the customer quote and due date, annual customer renewal date, actual registrar coverage/expiry dates, registrar reminder, actual registrar cost, historical-purchase flag, optional paying financial account, provider/reference, state, and audit fields. Customer renewal remains annual even when registrar coverage spans multiple years. `customer_price` may be set while all purchase fields remain null. See `database/schema.sql` for the executable definition.

---

## 8.5 payments Table

```sql
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
```

The `payment_type` and `payment_method` columns are retained as internal compatibility fields and receive default values from the API.

### 8.5.1 financial_accounts Table

Stores user-defined named balance holders, opening balance, and active/inactive status. Names are unique and no account names are pre-seeded.

### 8.5.2 financial_transactions Table

Stores Receive, Use, and Transfer movements. `project_payment_id` and `domain_billing_period_id` are nullable unique links. Payment Receive rows follow their payment; registrar Use rows follow their domain billing period. Generated rows cannot be edited directly.

---

## 8.6 recurring_fees Table

```sql
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
```

---

## 8.7 expenses Table

```sql
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    expense_date DATE NOT NULL,
    expense_category ENUM('Domain Purchase', 'Hosting Purchase', 'VPS Purchase', 'Server Cost', 'SSL Cost', 'API Cost', 'SMS Cost', 'Developer Cost', 'Design Cost', 'Transport', 'Other') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_to VARCHAR(255),
    payment_method ENUM('Cash', 'KPay', 'WavePay', 'Bank Transfer', 'AYA Pay', 'CB Pay', 'Other') DEFAULT 'Cash',
    reference_number VARCHAR(150),
    domain_billing_period_id INT UNIQUE,
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

---

## 8.8 invoices Table

```sql
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
    status ENUM('Draft', 'Sent', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled') DEFAULT 'Draft',
    notes TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

---

## 8.9 invoice_items Table

```sql
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);
```

---

## 8.10 receipts Table

```sql
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
```

---

## 8.11 settings Table

```sql
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 8.12 activity_logs Table

```sql
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100) NOT NULL,
    record_id INT,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 9. API Specification

All API responses must use JSON.

Base URL:

```text
/api
```

Standard success response:

```json
{
  "success": true,
  "message": "Success message",
  "data": {}
}
```

Standard error response:

```json
{
  "success": false,
  "message": "Error message",
  "errors": {}
}
```

---

## 9.1 Authentication APIs

### POST /api/auth/login

Request:

```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

Response:

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "jwt_token_here",
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@example.com",
      "role": "Admin"
    }
  }
}
```

### POST /api/auth/logout

Logs out the current user.

### GET /api/auth/me

Returns current logged-in user.

---

## 9.2 Project APIs

### GET /api/projects

Query parameters:

- search
- status
- payment_status
- date_from
- date_to
- page
- limit

### GET /api/projects/{id}

Returns project detail with calculated financial summary.

### POST /api/projects

Creates project.

### PUT /api/projects/{id}

Updates project.

### DELETE /api/projects/{id}

Deletes project.

### GET /api/projects/{id}/summary

Returns financial summary:

```json
{
  "contract_amount": 2000000,
  "discount_amount": 0,
  "tax_amount": 0,
  "total_payable": 2000000,
  "total_paid": 1200000,
  "remaining_balance": 800000,
  "payment_percentage": 60,
  "payment_status": "Partially Paid",
  "total_expenses": 300000,
  "profit": 900000
}
```

---

## 9.3 Payment APIs

### GET /api/payments

### GET /api/payments/{id}

### POST /api/payments

### PUT /api/payments/{id}

### DELETE /api/payments/{id}

### GET /api/projects/{id}/payments

Returns payments for a project.

### GET /api/financial-accounts

Returns named balance holders and calculated balances.

### POST /api/financial-accounts

Admin-only creation of a named financial account.

### PUT /api/financial-accounts/{id}

Admin-only account name, opening balance, and status update.

### DELETE /api/financial-accounts/{id}

Admin-only deletion of an unused account. Accounts with history must be set to Inactive.

### GET /api/financial-transactions

Supports search, movement, account, date, and pagination filters.

### GET /api/financial-transactions/{id}

### POST /api/financial-transactions

Creates a manual Use or Transfer. Manual Receive is not allowed.

### PUT /api/financial-transactions/{id}

Updates a manual Use or Transfer. A linked Receive is read-only.

### DELETE /api/financial-transactions/{id}

Deletes a manual Use or Transfer. A linked Receive can only be deleted through its project payment.

---

## 9.4 Recurring Fee APIs

### GET /api/recurring-fees

### GET /api/recurring-fees/{id}

### POST /api/recurring-fees

### PUT /api/recurring-fees/{id}

### DELETE /api/recurring-fees/{id}

### POST /api/recurring-fees/{id}/mark-paid

Marks fee as paid and updates next due date.

### Annual Domain Billing APIs

- `GET|POST /api/domain-billings`
- `GET|PUT|DELETE /api/domain-billings/{id}`
- `POST /api/domain-billings/{id}/customer-payment`
- `POST /api/domain-billings/{id}/purchase` to create or correct registrar accounting; `is_historical_purchase=1` omits the financial account movement
- `DELETE /api/domain-billings/{id}/purchase` to reverse linked registrar accounting
- `POST /api/domain-billings/{id}/renew` to create the next quoted annual period

All multi-record payment and purchase operations must use a database transaction.

---

## 9.5 Expense APIs

### GET /api/expenses

### GET /api/expenses/{id}

### POST /api/expenses

### PUT /api/expenses/{id}

### DELETE /api/expenses/{id}

### GET /api/projects/{id}/expenses

---

## 9.6 Invoice APIs

### GET /api/invoices

### GET /api/invoices/{id}

### POST /api/invoices

### PUT /api/invoices/{id}

### DELETE /api/invoices/{id}

### GET /api/invoices/{id}/print

Returns print-ready invoice data.

---

## 9.7 Receipt APIs

### GET /api/receipts/next-number

Returns the next server-controlled receipt number.

### GET /api/receipts

### GET /api/receipts/{id}

### POST /api/receipts

### PUT /api/receipts/{id}

### DELETE /api/receipts/{id}

---

## 9.8 Dashboard APIs

### GET /api/dashboard/summary

Returns:

- total_projects
- active_projects
- completed_projects
- total_contract_value
- total_received
- total_outstanding
- this_month_income
- this_month_expenses
- net_profit
- overdue_payments
- due_recurring_fees

### GET /api/dashboard/charts

Returns chart data.

### GET /api/dashboard/recent-activity

Returns recent payments, projects, and reminders.

### GET /api/reminders

Returns due, overdue, domain-renewal, outstanding-balance, and invoice reminders grouped by type.

### POST /api/reminders/resolve

Dismisses a reminder for the current user-facing reminder center.

---

## 9.9 Report APIs

### GET /api/reports/project-financial

### GET /api/reports/payment-collection

### GET /api/reports/outstanding-balance

### GET /api/reports/expense

### GET /api/reports/profit

### GET /api/reports/recurring-fees

### GET /api/reports/monthly-income-expense

### GET /api/reports/invoice

### GET /api/reports/financial-overview

---

## 10. Layout Specification

---

## 10.1 General Responsive Layout

### Compact Data Entry

- Daily forms must show only the fields required to complete the common workflow.
- Project entry shows the essential project, customer contact, contract, and due-date fields; domain/server fields remain available in a collapsed optional section.
- Payments use project, date, amount, type, and method.
- Expenses use project, date, category, and amount.
- Recurring fees use project, type, amount, billing cycle, and next due date; the API derives the fee name and status when needed.
- Receipts use project, linked payment, date, and amount; numbering, method, receiver, and available amount are server-controlled or derived.
- Optional data already stored on an edited record must be preserved even when it is not shown in the compact form.
- Growing project and financial lists must use server-side filtering, counting, stable ordering, and pagination.

The application must support:

- Desktop
- Tablet
- Mobile

### Desktop Layout

Desktop screen uses:

- Left sidebar
- Topbar
- Main content area

Sidebar width: around 240px.

Topbar height: around 64px.

### Mobile Layout

Mobile screen uses:

- Topbar
- Hamburger menu
- Slide-out sidebar
- Card-based tables where needed

Tables must become mobile-friendly by either:

1. Horizontal scroll, or
2. Card list layout

---

## 10.2 Sidebar Menu

Sidebar items:

1. Dashboard
2. Projects
3. Payments
4. User Financial
5. Recurring Fees
6. Expenses
7. Invoices
8. Receipts
9. Reminders
10. Reports
11. Users
12. Settings

---

## 10.3 Dashboard Layout

Dashboard structure:

```text
-------------------------------------------------
Topbar
-------------------------------------------------
Sidebar | Summary Cards
        | Charts Row
        | Recent Payments | Upcoming Renewals
        | Overdue Balances | Recent Projects
-------------------------------------------------
```

Summary card grid:

- Desktop: 4 cards per row
- Tablet: 2 cards per row
- Mobile: 1 card per row

---

## 10.4 Projects Page Layout

```text
Projects
[Create Project Button]

Filters:
[Search] [Status] [Payment Status] [Date From] [Date To]

Table:
Project Code | Project Name | Customer | Status | Contract | Paid | Balance | Payment Status | Delivery Date | Actions
```

Mobile layout:

Each project becomes a card:

```text
Project Name
Customer Name
Status Badge | Payment Badge
Contract: xxx
Paid: xxx
Balance: xxx
[View] [Edit]
```

---

## 10.5 Project Detail Layout

```text
Project Name
Project Code | Status | Payment Status

Financial Summary Cards:
Contract | Paid | Balance | Expense | Profit

Tabs:
Overview | Payments | Recurring Fees | Expenses | Invoices | Receipts | Notes
```

---

## 10.6 Project Form Layout

Use sections:

1. Basic Information
2. Customer Information
3. Financial Information
4. Initial Server Billing (Domain + Hosting), shown only while creating a project
5. Technical Information
6. Notes

The initial server section is optional. When enabled, saving creates the project and one quoted Domain Billing period atomically. Legacy project-level domain dates and prices are not editable in this form; existing legacy values remain readable in project details.

Each section should be collapsible on mobile if possible.

---

## 10.7 Payment Page Layout

```text
Payments
[Add Payment Button]

Filters:
[Date From] [Date To] [Project] [Received By]

Table:
Date | Project | Customer | Amount | Received By | Recorded By | Actions
```

---

## 10.8 Recurring Fees Page Layout

```text
Recurring Fees
[Add Fee Button]

Filters:
[Search] [Fee Type] [Status] [Due Date Range]

Table:
Project | Fee Name | Type | Amount | Cycle | Next Due Date | Status | Actions
```

Status badges:

- Not Due: Neutral
- Due Soon: Warning
- Due Today: Important
- Overdue: Danger
- Paid: Success

---

## 10.9 Invoice Layout

Invoice list:

```text
Invoice No | Date | Project | Customer | Total | Paid | Balance | Status | Actions
```

Invoice softcopy layout must be clean, A4-formatted, suitable for saving as PDF, and shown in a full-screen preview. Template components are customized from Settings.

---

## 10.10 Receipt Layout

Receipt list:

```text
Receipt No | Date | Project | Customer | Amount | Method | Received By | Actions
```

Receipt records use the normal responsive list/form interface and have no printable document layout.

---

## 11. Calculation Rules

### 11.1 Project Total Payable

```text
Total Payable = Contract Amount - Discount Amount + Tax Amount
```

### 11.2 Total Paid

```text
Total Paid = SUM(all payments for project)
```

### 11.3 Remaining Balance

```text
Remaining Balance = Total Payable - Total Paid
```

If remaining balance is below zero, show it as overpaid.

### 11.4 Profit

```text
Profit = Total Paid - Total Expenses
```

### 11.5 Expected Profit

```text
Expected Profit = Total Payable - Total Expenses
```

### 11.6 Payment Percentage

```text
Payment Percentage = (Total Paid / Total Payable) * 100
```

If total payable is zero, payment percentage is 0.

---

## 12. Validation Rules

### 12.1 Project Validation

Required fields:

- Project code
- Project name
- Status
- Contract amount

Rules:

- Contract amount cannot be negative.
- Discount cannot be negative.
- Tax cannot be negative.
- Delivery date cannot be earlier than start date.

### 12.2 Payment Validation

Required fields:

- Project ID
- Payment date
- Amount
- Payment type
- Payment method

Rules:

- Amount must be greater than zero.

### 12.3 Expense Validation

Required fields:

- Project ID
- Expense date
- Expense category
- Amount

Rules:

- Amount must be greater than zero.

### 12.4 Recurring Fee Validation

Required fields:

- Project ID
- Fee name
- Fee type
- Amount
- Billing cycle
- Next due date

Rules:

- Amount cannot be negative.

### 12.5 Invoice Validation

Required fields:

- Project ID
- Invoice number
- Invoice date
- Invoice type
- At least one invoice item

---

## 13. Security Requirements

1. All protected APIs must require authentication.
2. Passwords must be hashed.
3. SQL queries must use prepared statements.
4. Validate all input on backend.
5. Escape output where needed.
6. Prevent unauthorized access by role.
7. Do not expose database errors directly to users.
8. Use CORS properly for frontend/backend communication.

---

## 14. Non-Functional Requirements

### 14.1 Performance

- Dashboard should load within 3 seconds for normal data volume.
- Lists should support pagination.
- API response should be optimized with indexes.

### 14.2 Usability

- UI must be simple and clean.
- Important financial values must be easy to see.
- Use badges for statuses.
- Use confirmation dialogs before deleting.

### 14.3 Compatibility

Support latest versions of:

- Chrome
- Edge
- Firefox
- Android mobile browser

### 14.4 Responsiveness

The system must be usable on:

- 1366px desktop
- 1024px tablet
- 768px tablet
- 390px mobile

---

## 15. Initial Seed Data

Create default admin user:

```text
Name: Admin
Email: admin@example.com
Password: admin123456
Role: Admin
```

The password must be hashed in the database.

Create default settings:

```text
company_name = Your Company Name
currency = MMK
invoice_prefix = INV
receipt_prefix = REC
project_prefix = PRJ
invoice_design = validated JSON template (created when the designer is saved)
```

---

## 16. Implementation Order for AI Coding Agent

The AI coding agent should implement in this order:

1. Create MySQL database and tables.
2. Create PHP database connection.
3. Create authentication API.
4. Create React login page.
5. Create protected layout with sidebar and topbar.
6. Create project CRUD backend APIs.
7. Create project list, create, edit, and detail pages.
8. Create payment CRUD APIs and UI.
9. Add project financial summary calculation.
10. Create recurring fee APIs and UI.
11. Create expense APIs and UI.
12. Create dashboard APIs and UI.
13. Create invoice APIs and UI.
14. Create receipt APIs and UI.
15. Create reminder page.
16. Create report pages.
17. Add responsive mobile layout.
18. Add role-based permissions.
19. Add validation and error handling.
20. Final testing.

---

## 17. Acceptance Criteria

The system is accepted when:

1. Admin can login successfully.
2. Admin can create, edit, delete, and view projects.
3. Project can store customer info without a separate client module.
4. Payments can be added directly to a project.
5. Project paid amount and balance are calculated automatically.
6. Recurring server/domain/hosting fees can be tracked.
7. Due and overdue fees are visible in reminders.
8. Expenses can be added to a project.
9. Profit can be calculated from paid amount and expenses.
10. Dashboard shows correct financial summary.
11. Invoices can be created, previewed, and saved as A4 PDF softcopies.
12. Receipt records can be created and managed without print or document-generation actions.
13. Reports can be filtered by date range.
14. UI works on desktop and mobile.
15. Viewer role cannot modify data.
16. Staff role cannot manage users.
17. Delete actions require confirmation.
18. Backend validates all important inputs.
19. SQL injection is prevented using prepared statements.
20. System can run locally with React frontend and PHP backend.

---

## 18. Future Version Ideas

These features may be added later:

1. Separate client/customer module
2. Task management
3. Kanban board
4. Project milestones
5. File uploads
6. Telegram notification
7. Email notification
8. PDF export
9. Mobile app
10. Multi-company support
11. Advanced accounting
12. Staff salary and payroll
13. Customer portal

---

## 19. Important Development Notes

1. Do not build task management in Version 1.
2. Do not build a separate client module in Version 1.
3. Project is the main entity.
4. Customer details must be saved inside the project table.
5. Every payment, expense, invoice, receipt, and recurring fee must belong to a project.
6. Always calculate financial summary dynamically from payments and expenses.
7. Do not manually store total paid or remaining balance unless using cached values carefully.
8. Keep the UI simple and responsive.
9. Use reusable React components.
10. Keep backend API responses consistent.

---

# End of SRS
