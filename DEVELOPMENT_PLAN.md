# KSSPM Version 1.7 Master Development Plan

This document is the complete step-by-step implementation plan for `SRS.md`. It covers the full Version 1 system through Version 1.7, including project-owner contacts, separate customer/registrar domain renewal schedules, unified recurring-fee tracking, historical imports, user financial accounts, reports, and the A4 invoice editor.

## 1. Product Boundaries

### 1.1 Core Product Rules

- The project is the central business entity.
- Customer and project-owner details are stored directly in each project.
- Payments, expenses, recurring fees, invoices, and receipts must belong to a project.
- Financial totals are calculated from source transactions rather than manually maintained balances.
- The interface must remain simple enough for a small software company.

### 1.2 Included Scope

- Authentication and role-based access
- Dashboard financial summaries and charts
- Project CRUD and project financial summaries
- Project-owner/customer contact information
- Payments and payment status calculation
- Recurring domain, hosting, server, SSL, maintenance, and subscription fees
- Domain purchase, reminder, actual payment dates, and domain/server pricing
- Project expenses and profit calculations
- Project-linked invoices and invoice items
- Internal receipt records without printable document output
- Financial reminders
- Reports, filters, printing, and CSV export
- User management and company settings
- Responsive phone, tablet, and desktop layouts

### 1.3 Explicitly Excluded

- Task management, milestones, or Kanban
- Separate customer/client module
- Chat or messaging
- Payroll or salary management
- Inventory
- Advanced accounting ledger
- Customer portal
- Native mobile application
- Multi-company support

## 2. Technology and Architecture

### 2.1 Stack

- Frontend: React with Vite
- Backend: dependency-free plain PHP REST API
- Database: MySQL/MariaDB through PHP PDO
- Authentication: signed bearer token with hashed passwords
- Styling: responsive plain CSS
- Icons: Lucide React
- HTTP client: browser Fetch API

### 2.2 Runtime Layout

```text
Browser
  -> React + Vite frontend
  -> JSON REST requests
  -> Apache + PHP API
  -> PDO prepared statements
  -> MySQL ksspm database
```

### 2.3 Project Structure

```text
ksspm/
  backend/
    config/
    helpers/
    middleware/
    public/
    routes/
  database/
    schema.sql
    migrate_v1_1.sql
  frontend/
    public/
    src/
      api/
      assets/
      utils/
  DEVELOPMENT_PLAN.md
  README.md
  SRS.md
  invoice-generator-a4-fixed.html
```

### 2.4 REST Endpoint Inventory

Authentication:

- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`

Projects and nested finance:

- `GET|POST /projects`
- `GET|PUT|DELETE /projects/{id}`
- `GET /projects/{id}/summary`
- `GET /projects/{id}/payments`
- `GET /projects/{id}/expenses`

Financial records:

- `GET /financial-accounts`
- `POST /financial-accounts`
- `PUT /financial-accounts/{id}`
- `DELETE /financial-accounts/{id}`
- `GET|POST /financial-transactions`
- `GET|PUT|DELETE /financial-transactions/{id}`
- `GET|POST /payments`
- `GET|PUT|DELETE /payments/{id}`
- `GET|POST /recurring-fees`
- `GET|PUT|DELETE /recurring-fees/{id}`
- `POST /recurring-fees/{id}/mark-paid`
- `GET|POST /expenses`
- `GET|PUT|DELETE /expenses/{id}`

Invoices and receipts:

- `GET|POST /invoices`
- `GET /invoices/next-number`
- `GET|PUT|DELETE /invoices/{id}`
- `GET /invoices/{id}/print`
- `GET|POST /receipts`
- `GET /receipts/next-number`
- `GET|PUT|DELETE /receipts/{id}`

Dashboard, reminders, and reports:

- `GET /dashboard/summary`
- `GET /dashboard/charts`
- `GET /dashboard/recent-activity`
- `GET /reminders`
- `POST /reminders/resolve`
- `GET /reports/project-financial`
- `GET /reports/payment-collection`
- `GET /reports/outstanding-balance`
- `GET /reports/expense`
- `GET /reports/profit`
- `GET /reports/recurring-fees`
- `GET /reports/invoice`
- `GET /reports/monthly-income-expense`
- `GET /reports/financial-overview`

Administration:

- `GET|POST /users`
- `PUT|DELETE /users/{id}`
- `GET|POST /settings`

Every endpoint must use the standard SRS JSON envelope and the appropriate authentication, role, validation, filtering, and not-found behavior.

## 3. Phase 1: Repository and Environment Setup

1. Create the frontend, backend, and database directories.
2. Initialize the React Vite frontend.
3. Configure the PHP public entry point and Apache rewriting.
4. Configure CORS for local Vite and Apache usage.
5. Create the PDO connection using environment values with XAMPP-compatible defaults.
6. Add a common JSON response format for success and failure responses.
7. Add JSON request-body parsing.
8. Add a frontend API client with bearer-token support and consistent error handling.
9. Document XAMPP, database, frontend, and API startup instructions.

Deliverables:

- Running React shell
- Reachable PHP API
- Working PDO connection
- Consistent JSON response contract
- Local setup instructions

## 4. Phase 2: Database Foundation

### 4.1 Fresh-Install Schema

Create `database/schema.sql` with UTF-8 support, foreign keys, defaults, and these tables:

1. `users`
2. `projects`
3. `payments`
4. `recurring_fees`
5. `expenses`
6. `invoices`
7. `invoice_items`
8. `invoice_sequences`
9. `receipts`
10. `receipt_sequences`
11. `resolved_reminders`
12. `settings`
13. `activity_logs`

### 4.2 Relationship Rules

- Link every financial record to `projects.id`.
- Cascade project deletion to its payments, fees, expenses, invoices, invoice items, and receipts where appropriate.
- Link creator/receiver fields to `users.id`.
- Enforce unique project codes, user emails, invoice numbers, receipt numbers, and setting keys.
- Use decimal fields for money and date fields for business dates.

### 4.3 Project Fields

Implement all basic, owner/customer, financial, technical, and domain/server fields from the SRS:

- Basic: code, name, type, description, status, priority, start, delivery, and completion dates
- Owner/customer: company name, contact person, phone, email, and address
- Financial: contract, upfront, discount, tax, due date, currency, and notes
- Domain/server: domain name, purchase date, one-year reminder date, actual payment date, and price
- Technical: hosting/server providers, server IP, repository, admin URL, production URL, and notes

### 4.4 Indexes

Add indexes for project status/payment due date, payment project/date, expense project/date, recurring fee due date/status, invoice status/due date, and common foreign-key lookups.

### 4.5 Seed Data

- Insert the default Admin user with a password hash for `admin123456`.
- Insert company, currency, invoice, receipt, and project-prefix settings.
- Insert company contact and payment settings used by invoices.

### 4.6 Existing Database Migration

- Provide a non-destructive `migrate_v1_1.sql`.
- Add domain/server project fields only when missing.
- Add `invoice_sequences` without deleting invoices.
- Add `receipt_sequences` and initialize it from existing receipt numbers.
- Add `resolved_reminders` so dismissed reminders persist.
- Upgrade the activity-log user foreign key to preserve logs when a user is removed.
- Initialize the current invoice sequence from existing invoice numbers.
- Insert missing settings without overwriting configured values.
- Require a backup before production migration.

## 5. Phase 3: Authentication and Authorization

1. Implement login by email and password.
2. Verify passwords with `password_verify()` and reject inactive users.
3. Generate a signed bearer token containing user identity and role.
4. Implement token parsing, signature validation, and expiry validation.
5. Implement current-user and logout endpoints.
6. Protect every non-authentication endpoint.
7. Add write protection for Viewer users.
8. Restrict user and settings management to Admin users.
9. Hide unauthorized frontend commands as well as enforcing permission in PHP.

| Capability | Admin | Staff | Viewer |
| --- | --- | --- | --- |
| View dashboard/projects/reports | Yes | Yes | Yes |
| Create/update daily records | Yes | Yes | No |
| Delete financial/project records | Yes | As permitted by API policy | No |
| Manage users | Yes | No | No |
| Change settings | Yes | No | No |

## 6. Phase 4: Application Shell and Responsive Navigation

1. Build the login screen with validation and error feedback.
2. Build the protected application shell.
3. Add the 240px desktop sidebar and topbar.
4. Add all SRS navigation entries in the required order.
5. Add current-user identity, role, and logout command.
6. Add active-navigation styling and Lucide icons.
7. Build a slide-out mobile sidebar controlled by a menu button.
8. Ensure navigation works with keyboard focus and meaningful labels.
9. Add reusable cards, panels, badges, modals, forms, filters, tables, empty states, and alerts.

Responsive targets are 1366px desktop, 1024px tablet landscape, 768px tablet portrait, and 390px phone.

## 7. Phase 5: Project Management

### 7.1 Backend

1. Implement project list, detail, create, update, and delete endpoints.
2. Add project summary, project payments, and project expenses endpoints.
3. Support search across code, name, and customer/company.
4. Support status, payment status, and date-range filters.
5. Add pagination parameters and stable ordering for larger lists.
6. Set `created_by` from the authenticated user.
7. Calculate domain reminder as purchase date plus one year when omitted.
8. Validate and atomically create an optional first quoted Domain Billing period from the create-project request.
9. Return the created Domain Billing ID and ignore create-only server billing fields during later project updates so edits cannot duplicate periods.

### 7.2 Financial Calculations

```text
Total Payable = Contract Amount - Discount + Tax
Total Paid = SUM(Project Payments)
Remaining Balance = Total Payable - Total Paid
Payment Percentage = Total Paid / Total Payable * 100
Profit = Total Paid - Total Expenses
Expected Profit = Total Payable - Total Expenses
```

Payment status order:

1. Fully Paid when paid is greater than or equal to payable.
2. Overdue when a positive balance exists after the due date.
3. Partially Paid when some payment exists.
4. Unpaid when no payment exists.

Show a negative remaining balance as an overpayment rather than silently discarding it.

### 7.3 Frontend

1. Build the project list with all required financial and owner columns.
2. Add search, project status, payment status, and date filters.
3. Add create, view, edit, and confirmed-delete actions.
4. Divide the form into Basic, Project Owner/Customer, Financial, create-only Initial Server Billing (Domain + Hosting), Technical, and Notes sections.
5. Use proper date, email, telephone, number, textarea, and select controls.
6. Default the initial server quote date, First Year label, and reminder lead time while keeping them editable.
7. Build detail tabs for Overview, Payments, Recurring Fees, Expenses, Invoices, Receipts, and Notes.
8. Show Contract, Paid, Balance, Expenses, and Profit summary cards.
9. Show owner contact and domain/server information prominently.

## 8. Phase 6: Payments

### 8.1 Backend

1. Implement list, detail, create, update, and delete endpoints.
2. Validate project, date, positive amount, payment stage, and an active Received By financial account for current payments.
3. Set the audit `received_by` field from the authenticated user.
4. Create, update, or delete the linked Receive transaction atomically with the payment.
5. Support project, financial account, and date-range filters.
6. Return project code/name, customer, financial receiver, and recording user with list rows.
7. Ensure project and account calculations immediately reflect payment changes.

### 8.2 Frontend

1. Build the payment table and add/edit form.
2. Keep the form focused on project, date, amount, payment stage, Historical Payment, and conditional Received By.
3. Add project, receiver, and date filters.
4. Show date, project, amount, financial receiver, and recording user.
5. Confirm deletions and refresh affected financial summaries.

### 8.3 User Financial

1. Create simple user-defined named financial accounts; do not seed fixed names or require account types or payment methods.
2. Create Receive, Use, and Transfer transaction storage with a unique optional project-payment link.
3. Allow manual creation and editing only for Use and Transfer.
4. Prevent direct changes to linked Receive rows and manage them from project payments.
5. Calculate opening balance plus incoming movements minus outgoing movements for every account.
6. Build balance cards and paginated transaction history with search, account, movement, date, and Today/Week/Month/Lifetime filters.
7. Build responsive Use Money and Transfer forms for phone, tablet, and desktop.
8. Keep transfers out of project revenue and expense calculations.
9. Add Admin CRUD for account name, opening balance, and status; prevent deletion when financial history exists.

### 8.5 Historical Payment Import

1. Allow Project and Domain payments to be marked Historical with no financial account.
2. Count historical payments in paid totals, calculated status, income, profit, dashboard, and dated reports.
3. Never create a User Financial Receive or change account balances for historical payments.
4. Support Upfront, Progress Payment, Final Payment, and Other stages in the payment form.
5. Allow an old fully paid project to be settled with one historical Final Payment.

### 8.4 Linked Domain Accounting

1. Distinguish Project, Domain, and Recurring payment scopes so domain collections do not reduce contract balances.
2. Generate one read-only Receive movement for each customer domain payment.
3. Generate one Domain Purchase expense and one Use movement when the registrar purchase is saved.
4. Upsert linked registrar accounting when cost or account is corrected, preventing duplicate deductions.
5. Reverse both linked rows when a registrar purchase is reversed.

## 9. Phase 7: Recurring Fees and Domain/Server Tracking

### 9.1 Backend

1. Implement recurring-fee CRUD.
2. Implement all SRS fee types and billing cycles.
3. Calculate Not Due, Due Soon, Due Today, Overdue, Paid, and Cancelled status from dates.
4. Implement mark-as-paid behavior.
5. Optionally create a customer payment when a fee is marked paid.
6. Update last-paid date and calculate the next due date from the billing cycle.
7. Support project, fee type, status, and due-date filters.
8. Honor Auto create reminder in reminder and dashboard queries without removing the fee from financial reports.
9. Keep direct recurring-fee rows as Manual and expose priced Domain Billing periods as derived, read-only Server rows.
10. Keep project updates separate from Domain Billing so they never create duplicate automatic fee rows.

### 9.2 Frontend

1. Build the recurring-fee table and add/edit form.
2. Show project, fee, type, price, cycle, last paid, next due, and status.
3. Add status badges with neutral, warning, danger, and success states.
4. Add a Mark Paid command for authorized users.
5. Keep the project domain/server snapshot conceptually aligned with recurring-fee history.
6. Expose fee name, reminder lead time, and the Auto create reminder checkbox in the responsive form.
7. Merge annual Domain Billing periods into this screen as derived read-only rows with a Source filter and domain-detail action.
8. Never insert derived domain rows into `recurring_fees` or include them twice in reports and reminders.

### 9.3 Annual Domain Billing

1. Create `domain_billing_periods` with quote, annual coverage, customer collection, registrar cost, account, and renewal fields.
2. Allow quote creation before the domain is purchased and allow customer collection before purchase.
3. Validate positive prices/costs, active financial accounts, valid dates, and customer-payment overage.
4. Derive one-year coverage and renewal reminder dates with leap-day handling.
5. Build list, detail, quote, customer-payment, purchase/correction/reversal, and renew endpoints.
6. Build a responsive Domain Billing screen with project, payment, purchase, and search filters.
7. Add annual periods to project detail, reminders, dashboard upcoming renewals, and financial reports.
8. Report quoted price, customer paid/balance, actual registrar cost, and realized/expected domain profit separately.
9. Allow already-bought domains to be imported as historical purchases with a project expense but no User Financial account movement.
10. Track the annual customer renewal date separately from the actual registrar expiry date so discounted multi-year registrations still bill customers yearly.
11. Carry active registrar coverage into later annual customer periods without duplicating registrar expenses or financial Use movements.

## 10. Phase 8: Expenses

1. Implement expense list, detail, create, update, and delete endpoints.
2. Support every expense category from the SRS.
3. Validate project, date, category, and positive amount.
4. Set `created_by` from the authenticated user.
5. Support project, category, payment method, and date filters.
6. Include expenses in project profit and expected-profit calculations.
7. Build the expense table/form with date, project, category, amount, payee, method, creator, filters, and confirmed deletion.

## 11. Phase 9: Dashboard

### 11.1 Summary Cards

- Total, active, and completed projects
- Total contract value
- Total received and outstanding
- Current-month income and expenses
- Net profit
- Overdue project payments
- Server fees due this month
- Upcoming renewals

### 11.2 Charts

1. Return and render monthly income and expense series.
2. Return and render project payment-status distribution.
3. Return and render recurring-fee distribution by type/status.
4. Provide readable values when charts cannot load.

### 11.3 Activity Lists

- Recent payments
- Recently created projects
- Overdue project balances
- Upcoming domain/server/hosting renewals

Use four summary cards per desktop row, two per tablet row, and one per phone row.

## 12. Phase 10: Invoice Generator

### 12.1 Backend

1. Implement invoice list, detail, create, update, and delete endpoints.
2. Require a project, invoice date, invoice type, and at least one valid item.
3. Calculate item totals, subtotal, discount, tax, total, paid, and balance in PHP.
4. Ignore client-calculated totals as authoritative values.
5. Save invoice items in the same transaction as the invoice.
6. Return project owner/contact and company settings with A4 softcopy data.

### 12.2 Automatic Numbering

1. Store one sequence per calendar year.
2. Lock the sequence row while reserving a number.
3. Format numbers as `{prefix}-YYYY-NNNN`.
4. Reset the numeric sequence naturally for a new year.
5. Make invoice numbers read-only in React.
6. Keep the unique database constraint as duplicate protection.

### 12.3 Project-Linked Editor

1. Require project selection before save.
2. Fill customer/company, owner contact, project name, currency, and an initial line item from the project.
3. Support multiple items with description, quantity, and unit price.
4. Support type, dates, discount, tax/additional charge, paid amount, status, and notes.
5. Show calculated totals in a live preview.

### 12.4 A4 Softcopy Preview and PDF Output

Match `invoice-generator-a4-fixed.html` with a fixed 210mm x 297mm page, company heading/tagline, orange accent, metadata, payment callout, item table, totals, payment details, and contact footer.

On tablet and phone, scale the complete A4 page as one unit. When saving a PDF, remove application chrome and output at physical A4 size without screen scaling.

### 12.5 Full-Screen Template Designer

1. Open invoice create/edit and saved previews in a full-screen workspace.
2. Use one shared A4 renderer for live editing, saved preview, template design, and PDF output.
3. Split the template into header, metadata, callout, item table, totals, notes, and footer components.
4. Add click selection and drag positioning on the A4 canvas.
5. Add per-component visibility, position, dimensions, padding, margin, alignment, typography, line-height, and color controls.
6. Add page typography, foreground, background, and accent controls.
7. Add table header, border, padding, font-size, striped-row, and column-width controls.
8. Store the design as validated JSON in Settings and merge older or partial designs with safe defaults.
9. Add reset and persistent save actions with Admin-only write access.
10. Keep the designer usable on desktop, tablet, and phone without changing physical A4 PDF output.

## 13. Phase 11: Receipts

1. Implement receipt CRUD.
2. Require and validate project, payment, date, and positive amount.
3. Verify that the payment belongs to the selected project.
4. Set the receiving user from authentication.
5. Generate unique receipt numbers from the configured prefix.
6. Return payment reference, project/customer, and receiver details for the internal record view.
7. Build receipt list and form, filtering payment choices by project.
8. Do not add Print, Save PDF, Download, or receipt document-generation actions.

## 14. Phase 12: Reminder Center

1. Aggregate project payment due and overdue records.
2. Aggregate recurring domain, hosting, server, SSL, and maintenance renewals.
3. Include project domain reminder dates and domain/server price snapshots.
4. Include overdue invoice balances.
5. Group reminders into Due Today, Due This Week, Overdue, and Upcoming Renewals.
6. Show type, project, customer, amount, due date, and status.
7. Add View Project, Add Payment, and Mark Resolved actions where applicable.
8. Exclude resolved/cancelled records from active reminders.

## 15. Phase 13: Reports

### 15.1 Required Reports

1. Project financial report
2. Payment collection report
3. Outstanding balance report
4. Expense report
5. Profit report
6. Recurring fee report
7. Invoice report
8. Monthly income and expense report
9. Financial collection overview

### 15.2 Financial Collection Periods

- Today: received today; project and fee amounts due on or before today
- This Week: Monday through Sunday
- This Month: first through last calendar day
- Lifetime: all received payments and all currently collectible balances

### 15.3 Summary Values

- Amount received
- Project balance still to collect
- Recurring renewal fees due
- Total to collect
- Domain/server price total

Keep project balances, recurring fees, and domain/server snapshot prices separate to prevent double counting.

### 15.4 Filters and Output

1. Add date range, project, project status, payment status, fee type, and expense category filters where relevant.
2. Apply filtering in SQL when practical.
3. Show matching payment, outstanding-project, and recurring-fee tables.
4. Support browser printing and normalized CSV export.
5. Show the active period/date range in the report header.

## 16. Phase 14: User Management and Settings

### 16.1 Users

1. Admin can list, create, update, activate/inactivate, and delete users.
2. Hash passwords on creation and password change.
3. Enforce unique valid email addresses.
4. Prevent removal or deactivation of the only active Admin.
5. Never return password hashes.

### 16.2 Settings

Manage company name/tagline, company contact details, payment method/account, default currency, and project/invoice/receipt prefixes. Use these settings in invoice/receipt output and number generation. Restrict writes to Admin.

## 17. Phase 15: Validation and Error Handling

### 17.1 Validation

- Project: required code/name/status/contract; non-negative money; valid email; valid date order
- Payment: required project/date/amount/type/method; amount greater than zero
- Expense: required project/date/category/amount; amount greater than zero
- Fee: required project/name/type/amount/cycle/due date; amount non-negative
- Invoice: required project/date/type/items; positive quantities; non-negative prices
- Receipt: required matching project/payment/date/amount; amount greater than zero
- User: required name/email/role/status; valid unique email; secure password

### 17.2 Error Handling

1. Return appropriate 400, 401, 403, 404, 409, 422, and 500 responses.
2. Use the standard JSON envelope for every error.
3. Return field-level validation errors.
4. Do not expose SQL credentials, stack traces, or raw database errors.
5. Handle duplicate codes/numbers with understandable messages.
6. Show frontend loading, empty, validation, and API-error states.
7. Keep entered form data after recoverable errors.

## 18. Phase 16: Security Hardening

1. Use PDO prepared statements for every user-derived value.
2. Whitelist table names, columns, sort directions, statuses, and enum-like values.
3. Validate bearer-token signature and expiry.
4. Apply role checks in PHP, not only React.
5. Hash passwords using PHP password APIs.
6. Configure CORS only for intended origins and methods.
7. Escape rendered text and avoid unsafe HTML.
8. Put secrets and debug configuration outside source control.
9. Prevent mass assignment with explicit allowed fields.
10. Validate related-record ownership before nested financial records are saved.
11. Write `activity_logs` entries for login, create, update, delete, mark-paid, and settings/user administration actions.
12. Include actor, module, record ID, action, description, and timestamp without storing secrets or full sensitive payloads.

## 19. Phase 17: Responsive UI and Accessibility

### 19.1 Desktop

- Persistent sidebar/topbar, four-column summaries, dense tables, two-column forms, and full-screen invoice workspaces

### 19.2 Tablet

- Slide-out navigation, two-column summaries/forms, stacked invoice workspace, and scrollable or condensed tables

### 19.3 Phone

- Single-column forms/cards, labeled card-style tables, full-width filters, scrollable period control, scaled A4 preview, and responsive full-screen invoice workspaces

### 19.4 Accessibility and UX

- Labels for every control
- Keyboard-reachable commands and visible focus
- Sufficient contrast
- Status text in addition to color
- Confirmation before destructive actions
- No overlapping/clipped text at 390, 768, 1024, and 1366px

## 20. Phase 18: Performance and Data Volume

1. Keep dashboard queries aggregate-based and indexed.
2. Add server-side pagination to growing lists.
3. Return only required list columns.
4. Avoid one-query-per-row patterns.
5. Debounce search or use explicit filter actions.
6. Keep normal dashboard load below three seconds.
7. Test with representative project and transaction volumes.

## 21. Phase 19: Testing Strategy

### 21.1 Database Tests

- Fresh schema import
- Non-destructive migration
- Foreign keys and cascades
- Unique constraints
- Seed login and settings

### 21.2 API Tests

- Login variants and permission boundaries
- CRUD and validation for every module
- Financial calculations after payment/expense changes
- Overdue and fully-paid boundaries
- Recurring billing-cycle calculations
- Exact one-year domain reminder
- Invoice sequence allocation and duplicates
- Receipt/payment project consistency
- Report periods and filters
- Standard 404 and server errors

### 21.3 Frontend Tests

- Authentication and navigation
- CRUD workflows and confirmations
- Filters, loading, errors, and empty states
- Project tabs and summaries
- Recurring fee Mark Paid
- Invoice project fill, items, save, reopen, and softcopy PDF output
- Receipt record creation and management without document output
- Valid CSV export
- Settings reflected in print output

### 21.4 Responsive and Print Tests

Test 1366 x 768, 1024 x 768, 768 x 1024, and 390 x 844. Verify navigation, forms, tables, filters, dialogs, and A4 invoice softcopy PDF output. Confirm receipts expose no document-generation action.

### 21.5 Automated Checks

```powershell
Get-ChildItem backend -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
cd C:\xampp\htdocs\ksspm\frontend
npm run lint
npm run build
npm audit --omit=dev
```

## 22. Phase 20: Deployment and Handoff

1. Back up the target database.
2. Import `schema.sql` for a fresh install or migrations `v1_1` through `v1_9` in filename order for an upgrade.
3. Configure database credentials and token secret outside source control.
4. Build the production frontend.
5. Configure Apache frontend/API routing and Authorization header forwarding.
6. Disable debug details.
7. Perform production login and smoke tests.
8. Change the default Admin password.
9. Document backup, restore, update, and troubleshooting steps.

## 23. End-to-End Acceptance Checklist

- [x] Admin can log in and log out.
- [x] Inactive and invalid users are rejected.
- [x] Admin, Staff, and Viewer permissions match the SRS.
- [x] Projects can be created, viewed, edited, filtered, and deleted.
- [x] Owner/customer details work without a separate customer module.
- [x] Domain purchase, reminder, actual payment date, and price are stored and displayed.
- [x] Domain reminder defaults to exactly one year after purchase.
- [x] A yearly domain quote can be stored before the domain is purchased.
- [x] Customer domain payments support unpaid, partial, and paid states without changing the contract balance.
- [x] Current registrar purchase creates exactly one linked expense and one account Use transaction; historical purchase creates the expense without an account movement.
- [x] Correcting or reversing a purchase updates financial accounts without duplicate deductions.
- [x] Annual renewal preserves prior-year billing and accounting history.
- [x] Payments update balances, status, profit, and dashboard totals.
- [x] A current payment records its Received By account and creates exactly one linked User Financial Receive movement.
- [x] Historical project and domain payments update paid status without changing financial account balances.
- [x] User Financial tracks account balances, money used, and transfers without duplicate payment entry.
- [x] Admin can create, rename, activate/deactivate, and safely delete unused financial accounts.
- [x] Recurring fees calculate statuses and advance when paid.
- [x] Project saves do not generate recurring fees, and obsolete Project Generated rows are removed by migration.
- [x] Priced Domain Billing periods appear automatically as one read-only Server row in the unified recurring-fee list without double counting.
- [x] Expenses update project profit and reports.
- [x] Dashboard cards, charts, and activity lists use correct data.
- [x] Invoice numbers increase automatically and remain unique.
- [x] Invoices are project-linked, editable, reopenable, and downloadable as A4 PDF softcopies.
- [x] Invoice output follows the supplied reference style.
- [x] Receipts link to project payments and expose no print/download document action.
- [x] Reminders show due, overdue, and upcoming records.
- [x] Reports cover every required type and filter.
- [x] Today, Week, Month, and Lifetime totals are correct.
- [x] Domain/server prices can be filtered separately from balances.
- [x] Reports print and export valid CSV.
- [x] User/settings management is Admin-only.
- [x] Validation, prepared statements, and safe errors are in place.
- [x] Delete actions require confirmation.
- [x] UI works at all target screen sizes.
- [x] README provides complete setup, migration, and run instructions.
- [x] PHP syntax, lint, build, audit, and smoke tests pass.

## 24. Requirement Traceability

| SRS Area | Plan Phases |
| --- | --- |
| Scope, roles, architecture | 1-6 |
| Authentication | 5 |
| Dashboard | 11 |
| Projects and calculations | 7 |
| Payments | 8 |
| Recurring fees/domain tracking | 9 |
| Expenses | 10 |
| Invoices | 12 |
| Receipts | 13 |
| Reminders | 14 |
| Reports | 15 |
| Users and settings | 16 |
| Validation and security | 17-18 |
| Responsive compatibility | 19 |
| Performance | 20 |
| Testing and acceptance | 21-23 |
| Deployment and documentation | 22 |

## 25. Recommended Implementation Order

1. Environment and API foundation
2. Database schema and migration
3. Authentication and roles
4. Responsive application shell
5. Projects and financial calculations
6. Payments
7. Recurring fees and domain/server tracking
8. Expenses
9. Dashboard
10. Invoice generator
11. Receipts
12. Reminder center
13. Reports
14. Users and settings
15. Validation and security hardening
16. Responsive/accessibility refinement
17. Performance work
18. Full test pass
19. Deployment documentation and handoff

Do not begin excluded future modules until every Version 1.7 acceptance item is complete.
