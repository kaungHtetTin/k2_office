<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = dirname(__DIR__, 2);
$databaseDirectory = dirname(__DIR__);

require $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR
    . 'config' . DIRECTORY_SEPARATOR . 'database.php';

try {
    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations ('
        . 'migration VARCHAR(255) NOT NULL PRIMARY KEY,'
        . 'executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $migrationFiles = glob($databaseDirectory . DIRECTORY_SEPARATOR . 'migrate_v*.sql') ?: [];
    usort($migrationFiles, static function (string $left, string $right): int {
        preg_match('/migrate_v1_(\d+)\.sql$/', basename($left), $leftVersion);
        preg_match('/migrate_v1_(\d+)\.sql$/', basename($right), $rightVersion);

        return (int) ($leftVersion[1] ?? PHP_INT_MAX)
            <=> (int) ($rightVersion[1] ?? PHP_INT_MAX);
    });

    $completed = $pdo->query('SELECT migration FROM schema_migrations')
        ->fetchAll(PDO::FETCH_COLUMN);
    $completed = array_fill_keys($completed, true);
    $recordMigration = $pdo->prepare(
        'INSERT INTO schema_migrations (migration) VALUES (:migration)'
    );

    $count = 0;
    foreach ($migrationFiles as $migrationFile) {
        $migrationName = basename($migrationFile);

        if (isset($completed[$migrationName])) {
            fwrite(STDOUT, "SKIP  {$migrationName}" . PHP_EOL);
            continue;
        }

        $sql = file_get_contents($migrationFile);
        if ($sql === false) {
            throw new RuntimeException("Cannot read {$migrationName}");
        }

        fwrite(STDOUT, "RUN   {$migrationName}" . PHP_EOL);
        $pdo->exec($sql);
        $recordMigration->execute(['migration' => $migrationName]);
        fwrite(STDOUT, "DONE  {$migrationName}" . PHP_EOL);
        $count++;
    }

    fwrite(STDOUT, $count > 0
        ? "Completed {$count} migration(s)." . PHP_EOL
        : 'Database is already up to date.' . PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
