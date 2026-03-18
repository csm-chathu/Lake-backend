<?php

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/import-mysql-dump-to-sqlite.php <dump.sql> <target.sqlite>\n");
    exit(1);
}

$dumpPath = $argv[1];
$sqlitePath = $argv[2];

if (!is_file($dumpPath)) {
    fwrite(STDERR, "Dump file not found: {$dumpPath}\n");
    exit(1);
}

if (!is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite file not found: {$sqlitePath}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $sqlitePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$existingTables = [];
$tableQuery = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'");
foreach ($tableQuery->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
    $existingTables[strtolower((string) $tableName)] = true;
}

$contents = file_get_contents($dumpPath);
if ($contents === false) {
    fwrite(STDERR, "Unable to read dump file: {$dumpPath}\n");
    exit(1);
}

preg_match_all('/INSERT INTO\s+`?([a-zA-Z0-9_]+)`?\s*\([^;]*?\);/si', $contents, $matches, PREG_SET_ORDER);

$pdo->exec('PRAGMA foreign_keys = OFF');
$pdo->beginTransaction();

$inserted = 0;
$skippedMissingTable = 0;
$failed = 0;

foreach ($matches as $match) {
    $table = strtolower((string) ($match[1] ?? ''));
    $statement = (string) ($match[0] ?? '');

    if ($table === '' || $statement === '') {
        continue;
    }

    if (!isset($existingTables[$table])) {
        $skippedMissingTable++;
        continue;
    }

    $sqliteStatement = preg_replace('/`([^`]*)`/', '"$1"', $statement);
    if (is_string($sqliteStatement)) {
        $sqliteStatement = preg_replace('/^\s*INSERT\s+INTO\s+/i', 'INSERT OR REPLACE INTO ', $sqliteStatement);
    }
    if (!is_string($sqliteStatement) || trim($sqliteStatement) === '') {
        $failed++;
        continue;
    }

    try {
        $pdo->exec($sqliteStatement);
        $inserted++;
    } catch (Throwable $throwable) {
        $failed++;
        fwrite(STDERR, "Failed statement for table {$table}: " . $throwable->getMessage() . "\n");
    }
}

$pdo->commit();
$pdo->exec('PRAGMA foreign_keys = ON');

echo "Import complete\n";
echo "Inserted statements: {$inserted}\n";
echo "Skipped (missing table): {$skippedMissingTable}\n";
echo "Failed statements: {$failed}\n";
echo "Failed statements: {$failed}\n";
