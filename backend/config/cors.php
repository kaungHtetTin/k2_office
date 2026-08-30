<?php
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$configuredOrigins = trim((string)(getenv('CORS_ORIGIN') ?: ''));
$isProduction = (getenv('APP_ENV') ?: 'development') === 'production';
$allowedOrigin = '';
if ($configuredOrigins === '*' && !$isProduction) {
    $allowedOrigin = '*';
} elseif ($configuredOrigins !== '') {
    $origins = array_map('trim', explode(',', $configuredOrigins));
    if (in_array($requestOrigin, $origins, true)) $allowedOrigin = $requestOrigin;
} elseif (!$isProduction && $requestOrigin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $requestOrigin)) {
    $allowedOrigin = $requestOrigin;
}
if ($allowedOrigin !== '') header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isProduction && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
