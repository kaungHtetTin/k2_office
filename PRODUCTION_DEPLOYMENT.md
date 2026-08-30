# KSSPM Production Deployment

This guide targets the current Windows XAMPP installation. For an internet-facing business system, a maintained server or managed host with automatic security updates is preferable to a workstation running XAMPP.

## Already Prepared

- Production frontend is built directly into the repository deployment root.
- The build uses the same-origin /backend endpoint.
- API and frontend security headers are configured.
- Production mode rejects a missing or weak JWT secret.
- Login attempts are throttled after five failures in 15 minutes.
- Logout revokes the current token.
- Request bodies are limited to 1 MB.
- Database migrations through `database/migrate_v1_9_server_fee_cleanup.sql` have been applied to the current `ksspm` database.
- A verified snapshot was created under C:\xampp\backups\ksspm.

## 1. Choose the Production Address

Choose one real hostname, for example projects.example.com. Create its DNS record so it points to the server. Do not expose MySQL port 3306 or phpMyAdmin to the public internet.

For LAN-only deployment, use an internal hostname and a trusted internal TLS certificate. HTTPS is still required because the application carries financial and login data.

## 2. Change the Default Admin Password

1. Sign in as admin@example.com.
2. Open **Users**.
3. Edit the Admin user.
4. Enter a unique password of at least 14 characters.
5. Keep the role and status as Admin and Active.
6. Sign out and confirm the new password works.

Do this before allowing another person to reach the application.

## 3. Generate Private Secrets

Run this PowerShell command three times and keep each result private:

~~~powershell
$bytes = New-Object byte[] 48
[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
[Convert]::ToBase64String($bytes)
~~~

Use separate values for JWT_SECRET, the ksspm_app MySQL password, and the ksspm_backup MySQL password. Never store completed secrets inside the web document root or source control.

## 4. Create Restricted MySQL Users

1. Make a private copy of deploy/create-production-users.sql.example.
2. Replace both password placeholders with the generated MySQL passwords.
3. Run it as a MySQL administrator:

~~~powershell
Get-Content -Raw C:\private\create-ksspm-users.sql | C:\xampp\mysql\bin\mysql.exe -u root
~~~

4. Delete the temporary SQL file securely after verifying both accounts.
5. Use ksspm_app, never root, in the Apache production configuration.

## 5. Apply Database Migrations

Back up first. On an upgraded installation, apply migrations in order:

~~~powershell
Get-Content -Raw database\migrate_v1_1.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
Get-Content -Raw database\migrate_v1_2_security.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
Get-Content -Raw database\migrate_v1_3_domain_billing.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
Get-Content -Raw database\migrate_v1_4_historical_payments.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
Get-Content -Raw database\migrate_v1_5_historical_domain_purchases.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
Get-Content -Raw database\migrate_v1_6_domain_renewal_dates.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
Get-Content -Raw database\migrate_v1_7_unified_recurring_fees.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
Get-Content -Raw database\migrate_v1_8_performance_indexes.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
Get-Content -Raw database\migrate_v1_9_server_fee_cleanup.sql | C:\xampp\mysql\bin\mysql.exe -u root ksspm
~~~

All migrations are idempotent. Do not import database/schema.sql over real data because it recreates the tables.

## 6. Build the Frontend

~~~powershell
cd C:\xampp\htdocs\ksspm\frontend
npm ci
npm run lint
npm audit
npm run build
~~~

Deploy the repository root after running `npm run build` inside `frontend`; do not run the Vite development server in production. The production build expects the API at `/backend`.

## 7. Configure Apache and HTTPS

1. Copy the two VirtualHost blocks from deploy/apache-ksspm-vhost.conf.example into C:\xampp\apache\conf\extra\httpd-vhosts.conf.
2. Replace app.example.com with the chosen hostname.
3. Replace the TLS certificate paths with the real full-chain and private-key paths.
4. Replace DB_PASS and JWT_SECRET with the private values.
5. Keep APP_ENV production and APP_DEBUG 0.
6. Restrict read permissions on Apache configuration and certificate private keys.
7. Validate Apache:

~~~powershell
C:\xampp\apache\bin\httpd.exe -t
C:\xampp\apache\bin\httpd.exe -S
~~~

8. Restart Apache from the XAMPP Control Panel.
9. Confirm HTTP redirects to HTTPS and the browser reports a valid certificate.

Obtain the certificate from the hosting provider or a Windows ACME client. Do not use a self-signed certificate for public users.

## 8. Configure Automatic Backups

1. Copy deploy/mysql-backup.ini.example to C:\xampp\ksspm-backup.ini.
2. Replace its password and restrict the file to the backup operator and SYSTEM.
3. Test the included script:

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\ksspm\scripts\backup_database.ps1
~~~

4. Create a Windows Task Scheduler task to run that command nightly.
5. Copy backups to another machine or encrypted cloud storage.
6. Test restoration into a separate database monthly.

The script verifies non-empty output and retains 30 days by default.

## 9. Lock Down the Server

- Allow inbound TCP 443 and optionally 80 only.
- Block public access to ports 3306, 5173, and phpMyAdmin.
- Install Windows, Apache, PHP, MySQL, and dependency security updates.
- Keep display_errors off and inspect C:\xampp\apache\logs\ksspm-php-error.log.
- Give each person a separate KSSPM user.
- Use Viewer access for read-only users and deactivate departed users.
- Keep the server clock synchronized.

## 10. Run Production Verification

~~~powershell
powershell -NoProfile -ExecutionPolicy Bypass -File C:\xampp\htdocs\ksspm\scripts\production_smoke_test.ps1 -AppUrl https://projects.example.com -RequireHttps
~~~

Then manually verify:

1. Login and logout.
2. Create a small test project and payment.
3. Confirm the receiving financial account balance.
4. Create and save an invoice PDF.
5. Check the layout on a phone, tablet, and computer.
6. Delete the test business records.
7. Restore the newest backup into a separate test database.

Production approval should happen only after all seven checks pass.
