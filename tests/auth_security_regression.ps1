param(
    [string]$BaseUrl = 'http://127.0.0.1/ksspm/backend/public',
    [string]$DatabaseName = 'ksspm',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe'
)

$ErrorActionPreference = 'Stop'
$email = "throttle-$([DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds())@example.invalid"
$remoteAddress = '127.0.0.1'
$sha = [System.Security.Cryptography.SHA256]::Create()
$attemptKey = ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes("$email|$remoteAddress"))) -replace '-', '').ToLowerInvariant()
$sha.Dispose()

function Expect-LoginStatus([int]$Expected) {
    try {
        Invoke-WebRequest -UseBasicParsing -Method POST -Uri "$BaseUrl/auth/login" -ContentType 'application/json' -Body (@{ email = $email; password = 'deliberately-wrong' } | ConvertTo-Json -Compress) | Out-Null
        throw "Expected HTTP $Expected"
    } catch {
        if (-not $_.Exception.Response) { throw }
        $actual = [int]$_.Exception.Response.StatusCode
        if ($actual -ne $Expected) { throw "Expected HTTP $Expected, got $actual" }
    }
}

try {
    1..5 | ForEach-Object { Expect-LoginStatus 401 }
    Expect-LoginStatus 429
    Write-Output 'PASS: login throttling blocks the sixth attempt'
} finally {
    & $MysqlPath -u root -D $DatabaseName -e "DELETE FROM login_attempts WHERE attempt_key = '$attemptKey';" | Out-Null
}
