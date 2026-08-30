<?php
function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'ksspm';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $databaseTimezone = getenv('DB_TIMEZONE') ?: '+06:30';
    if (!preg_match('/^[+-](?:0\d|1[0-4]):[0-5]\d$/', $databaseTimezone)) {
        throw new RuntimeException('DB_TIMEZONE must use an offset such as +06:30.');
    }
    $pdo->exec("SET time_zone = " . $pdo->quote($databaseTimezone));

    return $pdo;
}
