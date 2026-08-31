# KSSPM Version 1

KSSPM is a project-centered financial management app for a small software development company. Version 1.7 includes project-owner contacts, separate annual customer and multi-year registrar domain schedules, unified recurring-fee tracking, historical imports, period-based collection reports, project-linked A4 invoices, and user-defined financial account balances.

## Stack

- Frontend: React + Vite
- Backend: plain PHP REST API with PDO
- Database: MySQL

## Local Setup

1. Keep the project at `C:\xampp\htdocs\ksspm` and start Apache and MySQL in XAMPP.
2. Open `http://localhost/phpmyadmin`, choose **Import**, select `C:\xampp\htdocs\ksspm\database\schema.sql`, and run the import. The script creates the `ksspm` database and its tables automatically.
3. Confirm the backend database defaults in `backend/config/database.php`.
   - Host: `127.0.0.1`
   - Database: `ksspm`
   - User: `root`
   - Password: empty
4. Install and run the frontend:

```bash
cd frontend
npm install
npm run dev
```

5. Open the Vite URL shown in the terminal, normally `http://localhost:5173`. The PHP API remains under Apache at `http://localhost/ksspm/backend/public`.

Default login:

- Email: `admin@example.com`
- Password: `admin123456`

Login sessions are browser-session-only by default. Selecting **Remember me on this device** stores the session persistently and uses the longer `JWT_REMEMBER_TTL_SECONDS` lifetime (30 days by default). Logout and authentication failures clear both session and persistent browser credentials.

### Upgrade an Existing Version 1 Database

Back up important data, then import migrations from `database/migrate_v1_1.sql` through `database/migrate_v1_11_manual_invoice_amounts.sql` in filename order. These idempotent migrations keep existing financial history, add the Version 1 features and performance indexes, remove obsolete Project Generated recurring rows, and add customizable installment invoices. Do not re-import `schema.sql` over a database containing real data because the fresh-install script recreates all tables.

In phpMyAdmin, select the existing `ksspm` database before importing each migration in filename order.

## API URL

The frontend defaults to:

```text
http://localhost/ksspm/backend/public
```

To change it, create `frontend/.env`:

```text
VITE_API_BASE_URL=http://localhost/ksspm/backend/public
```

The backend also works with PHP's built-in server:

```bash
php -S localhost:8000 -t backend/public backend/public/index.php
```

Then set:

```text
VITE_API_BASE_URL=http://localhost:8000
```

## Main Endpoints

- `POST /auth/login`
- `GET /dashboard/summary`
- `GET|POST /projects`
- `GET|PUT|DELETE /projects/{id}`
- `GET|POST /payments`
- `GET|POST /domain-billings`
- `GET|PUT|DELETE /domain-billings/{id}`
- `POST /domain-billings/{id}/customer-payment`
- `POST|DELETE /domain-billings/{id}/purchase`
- `POST /domain-billings/{id}/renew`
- `GET /financial-accounts`
- `POST /financial-accounts`
- `PUT|DELETE /financial-accounts/{id}`
- `GET|POST /financial-transactions`
- `GET|PUT|DELETE /financial-transactions/{id}`
- `GET|POST /recurring-fees`
- `POST /recurring-fees/{id}/mark-paid`
- `GET|POST /expenses`
- `GET|POST /invoices`
- `GET /invoices/next-number`
- `GET|POST /receipts`
- `GET /receipts/next-number`
- `GET /reminders`
- `POST /reminders/resolve`
- `GET /reports/{report-name}`
- `GET /reports/financial-overview?period=today|week|month|lifetime&project_id=&fee_type=`

All protected endpoints require `Authorization: Bearer <token>`.

## Assumptions

- Customer/company data is stored directly on each project.
- Project financial totals are calculated dynamically from payments and expenses.
- Recording a current project payment requires a Received By account and automatically creates the matching User Financial Receive row; no second entry is needed.
- Mark an old payment Historical to include it in paid totals and status without adding it to any current financial account. Use one historical Final Payment for an old fully paid contract, or historical Upfront/Progress payments for partial history.
- Historical customer domain payments work the same way: they settle the annual customer domain price without changing financial account balances.
- User Financial manually records only Use and Transfer movements. Admins manage user-defined account names, opening balances, and statuses without account types or payment methods.
- A fresh installation starts without financial accounts. An Admin creates the required names from **User Financial > Add Account** before recording the first payment.
- Viewer users can read but cannot create, update, or delete.
- Staff users can manage daily records but cannot manage users or settings.
- Invoices can be saved as A4 PDF softcopies through the browser PDF dialog. Receipt records do not have print or document-generation actions.
- Invoice numbers are allocated by the PHP API in the format `INV-YYYY-NNNN`. The year comes from the invoice issue date, and every new year starts independently at `0001`.
- Domain reminder date defaults to one year after domain purchase and remains editable.
- Annual Domain Billing stores the quoted customer price before purchase, accepts partial customer payments, and keeps domain income separate from the project contract balance.
- Create Project can optionally create the first combined Domain + Hosting server quote at the same time. Both records save atomically; later project edits cannot create duplicate periods.
- Each priced Domain Billing period appears automatically as one read-only `Server` row on the Recurring Fees screen. The customer price represents the combined domain and hosting/server charge, remains owned by Domain Billing, and is not inserted or counted a second time.
- Saving a project never generates a recurring fee. Existing `Project Generated` rows are removed by `database/migrate_v1_9_server_fee_cleanup.sql`; genuinely manual recurring fees remain available.
- Recording a registrar purchase creates one linked project expense and deducts the actual cost once from the selected financial account. Purchase correction updates those rows; reversal removes them.
- The A4 invoice appearance follows `invoice-generator-a4-fixed.html` and loads company/payment details from Settings.
- Invoice create/edit and preview use a full-screen A4 workspace. Admins can customize component positions, spacing, typography, colors, and table styling from **Settings > Open Invoice Designer**.
- The saved invoice template is used consistently by live previews and PDF softcopies; installations without a saved design use the built-in default.
- CSV export is implemented in the frontend reports page.
- This is a simple Version 1 REST API without Composer dependencies.

## Checks

Useful checks:

```powershell
Get-ChildItem backend -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
powershell -ExecutionPolicy Bypass -File tests\api_regression.ps1
powershell -ExecutionPolicy Bypass -File tests\auth_security_regression.ps1
cd frontend
npm run lint
npm run build
```

The API regression test uses the configured `ksspm` database, creates temporary business records, runs financial, CRUD, permission, reminder, pagination, invoice, and recurring-date assertions, and removes those business records afterward. Apache and MySQL must be running. Successful test actions remain in `activity_logs` by design, so use a disposable database when a completely untouched audit trail is required.

## Backup And Restore

Back up from a terminal before an upgrade:

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root ksspm > ksspm-backup.sql
```

Restore into an empty `ksspm` database with phpMyAdmin Import, or:

```powershell
Get-Content -Raw ksspm-backup.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
```

## Production Notes

- Follow the complete checklist in PRODUCTION_DEPLOYMENT.md.
- Run `npm run build` inside `frontend`; the production files are emitted into the repository root for direct deployment. The production API endpoint is `/backend`.
- Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_TIMEZONE`, `DB_TIMEZONE`, `JWT_TTL_SECONDS`, `JWT_REMEMBER_TTL_SECONDS`, and a strong `JWT_SECRET` in the server environment. The local defaults use `Asia/Yangon` and `+06:30`.
- Change the seeded Admin password after the first login.
- Keep PHP error display disabled on production and retain database backups before updates.
- Keep `activity_logs` as the audit trail. For very large installations, archive old rows only after a verified database backup; do not routinely delete current financial audit data.

## Troubleshooting

- If phpMyAdmin does not show `ksspm`, confirm MySQL is running and import `database/schema.sql` from phpMyAdmin's top-level Import screen.
- If the frontend reports a network error, open `http://localhost/ksspm/backend/public/auth/login`; a GET response such as an authentication or route error confirms Apache can reach PHP.
- If API requests return `Database connection failed`, verify the database name and credentials in `backend/config/database.php` or the `DB_*` environment variables.
- If a migrated database reports a missing column, table, or performance index, select `ksspm` and re-run all idempotent migrations through `database/migrate_v1_11_manual_invoice_amounts.sql` in filename order.
- If port 5173 is occupied, Vite chooses another port; use the URL printed by `npm run dev`.
