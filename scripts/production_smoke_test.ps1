param(
    [Parameter(Mandatory = $true)][string]$AppUrl,
    [string]$ApiUrl = "$AppUrl/api",
    [switch]$RequireHttps
)

$ErrorActionPreference = 'Stop'
if ($RequireHttps -and -not $AppUrl.StartsWith('https://')) { throw 'Production URL must use HTTPS.' }

$app = Invoke-WebRequest -UseBasicParsing -Uri $AppUrl
if ($app.StatusCode -ne 200 -or $app.Content -notmatch '<div id="root"></div>') { throw 'Frontend smoke test failed.' }

try {
    Invoke-WebRequest -UseBasicParsing -Uri "$ApiUrl/auth/me" | Out-Null
    throw 'Unauthenticated API request unexpectedly succeeded.'
} catch {
    if (-not $_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 401) { throw }
    $headers = $_.Exception.Response.Headers
    foreach ($name in @('X-Content-Type-Options', 'X-Frame-Options', 'Cache-Control')) {
        if (-not $headers[$name]) { throw "Missing API security header: $name" }
    }
}

Write-Output "PASS: frontend and protected API are reachable at $AppUrl"
