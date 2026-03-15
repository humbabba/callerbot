<?php

function getDb(): PDO {
    $dbPath = __DIR__ . '/callerbot.db';
    $db = new PDO("sqlite:{$dbPath}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode=WAL');

    $db->exec('CREATE TABLE IF NOT EXISTS query_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp TEXT NOT NULL,
        input TEXT NOT NULL,
        model TEXT,
        tool TEXT,
        output TEXT
    )');

    return $db;
}

function logQuery(string $input, ?string $model, ?string $tool, string $output): void {
    $db = getDb();

    // Arizona time (America/Phoenix — no DST)
    $tz = new DateTimeZone('America/Phoenix');
    $now = new DateTime('now', $tz);
    $timestamp = $now->format('Y-m-d g:i:s A');

    $stmt = $db->prepare('INSERT INTO query_log (timestamp, input, model, tool, output) VALUES (:ts, :input, :model, :tool, :output)');
    $stmt->execute([
        ':ts'     => $timestamp,
        ':input'  => $input,
        ':model'  => $model,
        ':tool'   => $tool,
        ':output' => $output,
    ]);

    // Prune to 500 entries
    $db->exec('DELETE FROM query_log WHERE id NOT IN (SELECT id FROM query_log ORDER BY id DESC LIMIT 500)');
}

function getLogEntries(int $limit = 500): array {
    $db = getDb();
    $stmt = $db->prepare('SELECT timestamp, input, model, tool, output FROM query_log ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
