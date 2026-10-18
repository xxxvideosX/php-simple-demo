<?php
// src/index.php

require __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

// 创建日志器，写入项目根目录的 app.log
$log = new Logger('api');
$log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Level::Debug));

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $db = new SQLite3(__DIR__ . '/../api.sqlite');
    $db->enableExceptions(true);
    $db->exec('CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $log->info('request', ['method' => $method, 'path' => $path]);

    // 返回前端页面
    if ($path === '/' || $path === '/index.html') {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/index.html');
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // 路由：/items 和 /items/{id}
    if (preg_match('#(?:^|/)items(?:/(\d+))?$#', $path, $m)) {
        $id = $m[1] ?? null;

        if ($method === 'GET' && $id === null) {
            $result = $db->query('SELECT * FROM items ORDER BY id DESC');
            $items = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $items[] = $row;
            }
            $log->debug('list items', ['count' => count($items)]);
            echo json_encode($items);
        }
        elseif ($method === 'GET' && $id !== null) {
            $stmt = $db->prepare('SELECT * FROM items WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($row) {
                $log->debug('get item', ['id' => $id]);
                echo json_encode($row);
            } else {
                $log->warning('item not found', ['id' => $id]);
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
        }
        elseif ($method === 'POST') {
            $name = $input['name'] ?? '';
            $stmt = $db->prepare('INSERT INTO items (name) VALUES (:name)');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->execute();
            $newId = $db->lastInsertRowID();
            $log->info('item created', ['id' => $newId, 'name' => $name]);
            http_response_code(201);
            echo json_encode(['id' => $newId]);
        }
        elseif ($method === 'PUT' && $id !== null) {
            $name = $input['name'] ?? '';
            $stmt = $db->prepare('UPDATE items SET name = :name WHERE id = :id');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            $changes = $db->changes();
            $log->info('item updated', ['id' => $id, 'name' => $name, 'changes' => $changes]);
            echo json_encode(['updated' => $changes]);
        }
        elseif ($method === 'DELETE' && $id !== null) {
            $stmt = $db->prepare('DELETE FROM items WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            $changes = $db->changes();
            $log->info('item deleted', ['id' => $id, 'changes' => $changes]);
            echo json_encode(['deleted' => $changes]);
        }
        else {
            $log->warning('method not allowed', ['method' => $method, 'path' => $path]);
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
    } else {
        $log->warning('route not found', ['path' => $path]);
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }

    $db->close();
} catch (\Throwable $e) {
    $log->error('unhandled exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}