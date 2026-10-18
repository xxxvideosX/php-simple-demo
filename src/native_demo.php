<?php

// native demo can not be web app, include web api stuff, it is failed to packaged
function main(): void
{
    // 1. Open (or create) the SQLite database
    $db = new SQLite3(__DIR__ . '/api.sqlite');
    $db->enableExceptions(true);

    // 2. Initialize the table (execute only on first run)
    $db->exec('CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    // 3. Simulate "request processing": insert a record here
    $stmt = $db->prepare('INSERT INTO items (name) VALUES (:name)');
    $stmt->bindValue(':name', 'demo item', SQLITE3_TEXT);
    $stmt->execute();

    // 4. Query and output JSON (simulating an API response)
    $result = $db->query('SELECT id, name, created_at FROM items ORDER BY id DESC');
    $items = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $row;
    }

    header('Content-Type: application/json'); // Ignored in CLI mode, but harmless
    echo json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    $db->close();
}