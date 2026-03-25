<?php
require_once __DIR__ . '/config/database.php';

$db = new Database();
try {
    $conn = $db->getConnection();
    echo "Connected successfully to database.\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
