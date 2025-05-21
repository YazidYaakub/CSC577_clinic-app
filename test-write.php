<?php
$dbPath = __DIR__ . '/database/healthcare.sqlite';

echo "<pre>";
echo "Checking DB write access...\n";

if (is_writable($dbPath)) {
    echo "✅ SQLite DB is writable\n";
} else {
    echo "❌ SQLite DB is NOT writable\n";
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['testuser_' . rand(100, 999), 'test', 'test@example.com', 'Test', 'User', 'patient']);
    echo "✅ Inserted test user successfully\n";
} catch (PDOException $e) {
    echo "❌ PDO Error: " . $e->getMessage() . "\n";
}

