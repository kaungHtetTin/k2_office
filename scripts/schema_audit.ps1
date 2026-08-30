param(
    [string]$MySqlPath = 'C:\xampp\mysql\bin\mysql.exe'
)

$ErrorActionPreference = 'Stop'
$databaseName = 'ksspm_schema_audit_' + [Guid]::NewGuid().ToString('N').Substring(0, 12)
if ($databaseName -notmatch '^ksspm_schema_audit_[a-f0-9]{12}$') { throw 'Unsafe audit database name.' }
if (-not (Test-Path -LiteralPath $MySqlPath -PathType Leaf)) { throw "mysql not found: $MySqlPath" }

$temporarySql = Join-Path ([IO.Path]::GetTempPath()) "$databaseName.sql"
$mysqlSourcePath = $temporarySql.Replace('\', '/')

function Invoke-SqlFile([string]$SourcePath) {
    $sql = (Get-Content -Raw -Encoding UTF8 -LiteralPath $SourcePath).
        Replace('CREATE DATABASE IF NOT EXISTS ksspm', "CREATE DATABASE IF NOT EXISTS $databaseName").
        Replace('USE ksspm;', "USE $databaseName;")
    [IO.File]::WriteAllText($temporarySql, $sql, [Text.UTF8Encoding]::new($false))
    & $MySqlPath -uroot --abort-source-on-error -e "source $mysqlSourcePath;"
    if ($LASTEXITCODE -ne 0) { throw "SQL audit failed: $SourcePath" }
}

try {
    Invoke-SqlFile (Join-Path $PSScriptRoot '..\database\schema.sql')
    Get-ChildItem (Join-Path $PSScriptRoot '..\database') -Filter 'migrate_v*.sql' |
        Sort-Object Name |
        ForEach-Object {
            Invoke-SqlFile $_.FullName
            Invoke-SqlFile $_.FullName
        }

    & $MySqlPath -uroot -D $databaseName --table -e @'
SELECT COUNT(*) tables_created FROM information_schema.tables WHERE table_schema=DATABASE();
SELECT COUNT(*) admin_seeded FROM users WHERE role='Admin' AND status='Active';
CHECK TABLE users,projects,domain_billing_periods,payments,recurring_fees,expenses,invoices,invoice_items,receipts,financial_accounts,financial_transactions,settings,resolved_reminders;
'@
    if ($LASTEXITCODE -ne 0) { throw 'Fresh database verification failed.' }
    Write-Output 'PASS: fresh schema and every migration imported twice successfully'
} finally {
    & $MySqlPath -uroot -e "DROP DATABASE IF EXISTS $databaseName;"
    if (Test-Path -LiteralPath $temporarySql) { Remove-Item -LiteralPath $temporarySql -Force }
}
