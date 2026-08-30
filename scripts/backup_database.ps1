param(
    [string]$BackupDirectory = 'C:\xampp\backups\ksspm',
    [string]$DefaultsFile = 'C:\xampp\ksspm-backup.ini',
    [string]$DatabaseName = 'ksspm',
    [int]$RetentionDays = 30,
    [string]$MySqlDumpPath = 'C:\xampp\mysql\bin\mysqldump.exe'
)

$ErrorActionPreference = 'Stop'
$backupRoot = [IO.Path]::GetFullPath($BackupDirectory)
New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null
if (-not (Test-Path -LiteralPath $DefaultsFile -PathType Leaf)) { throw "MySQL defaults file not found: $DefaultsFile" }
if (-not (Test-Path -LiteralPath $MySqlDumpPath -PathType Leaf)) { throw "mysqldump not found: $MySqlDumpPath" }

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$outputPath = Join-Path $backupRoot "$DatabaseName-$stamp.sql"
& $MySqlDumpPath "--defaults-extra-file=$DefaultsFile" --single-transaction --routines --events --triggers --no-tablespaces --default-character-set=utf8mb4 $DatabaseName --result-file=$outputPath
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $outputPath) -or (Get-Item -LiteralPath $outputPath).Length -eq 0) {
    throw 'Database backup failed or produced an empty file.'
}

$cutoff = (Get-Date).AddDays(-[Math]::Max(1, $RetentionDays))
Get-ChildItem -LiteralPath $backupRoot -File -Filter "$DatabaseName-*.sql" |
    Where-Object { $_.LastWriteTime -lt $cutoff -and [IO.Path]::GetFullPath($_.DirectoryName) -eq $backupRoot } |
    ForEach-Object { Remove-Item -LiteralPath $_.FullName -Force }

Write-Output "Backup created: $outputPath"
