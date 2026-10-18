<?php
// src/index.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$db = new SQLite3(__DIR__ . '/../api.sqlite');
$db->enableExceptions(true);
$db->exec('CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 返回前端页面
if ($path === '/' || $path === '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Routing: /items and /items/{id}
if (preg_match('#(?:^|/)items(?:/(\d+))?$#', $path, $m)) {
    $id = $m[1] ?? null;

    if ($method === 'GET' && $id === null) {
        $result = $db->query('SELECT * FROM items ORDER BY id DESC');
        $items = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $items[] = $row;
        echo json_encode($items);
    }
    elseif ($method === 'GET' && $id !== null) {
        $stmt = $db->prepare('SELECT * FROM items WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        echo $row ? json_encode($row) : json_encode(['error' => 'Not found']);
        if (!$row) http_response_code(404);
    }
    elseif ($method === 'POST') {
        $stmt = $db->prepare('INSERT INTO items (name) VALUES (:name)');
        $stmt->bindValue(':name', $input['name'] ?? '', SQLITE3_TEXT);
        $stmt->execute();
        echo json_encode(['id' => $db->lastInsertRowID()]);
        http_response_code(201);
    }
    elseif ($method === 'PUT' && $id !== null) {
        $stmt = $db->prepare('UPDATE items SET name = :name WHERE id = :id');
        $stmt->bindValue(':name', $input['name'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(['updated' => $db->changes()]);
    }
    elseif ($method === 'DELETE' && $id !== null) {
        $stmt = $db->prepare('DELETE FROM items WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(['deleted' => $db->changes()]);
    }
    else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}

$db->close();