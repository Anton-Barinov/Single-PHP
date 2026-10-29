<?php

declare(strict_types=1);

echo "=== Standalone SQLite Database Utility ===\n";

$dbFile = sys_get_temp_dir() . '/single_php_demo.sqlite';
$pdo = new PDO("sqlite:{$dbFile}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS notes (id INTEGER PRIMARY KEY, title TEXT, created_at TEXT)");

$action = $argv[1] ?? 'list';

if ($action === 'add') {
    $title = $argv[2] ?? 'Note created at ' . date('c');
    $stmt = $pdo->prepare("INSERT INTO notes (title, created_at) VALUES (:title, :created_at)");
    $stmt->execute(['title' => $title, 'created_at' => date('Y-m-d H:i:s')]);
    echo "✓ Added note: '{$title}'\n";
} else {
    $rows = $pdo->query("SELECT * FROM notes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Recent Notes (" . count($rows) . "):\n";
    foreach ($rows as $r) {
        echo "  [#{$r['id']}] {$r['title']} ({$r['created_at']})\n";
    }
}
echo "Database location: {$dbFile}\n";
