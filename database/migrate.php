<?php
require_once __DIR__ . '/../config/database.php';

echo "Starting database migration...\n";

// 1. Connect to MySQL (without selecting a specific database first)
try {
    // Read the database name from our Config class
    $db = new Database();
    
    // We need to connect without the database parameter first to create the DB
    // Use reflection to get the private credentials
    $reflection = new ReflectionClass($db);
    $hostProp = $reflection->getProperty('host');
    $userProp = $reflection->getProperty('username');
    $passProp = $reflection->getProperty('password');
    $dbProp = $reflection->getProperty('db_name');
    
    $hostProp->setAccessible(true);
    $userProp->setAccessible(true);
    $passProp->setAccessible(true);
    $dbProp->setAccessible(true);
    
    $host = $hostProp->getValue($db);
    $username = $userProp->getValue($db);
    $password = $passProp->getValue($db);
    $dbName = $dbProp->getValue($db);
    
    $conn = new PDO("mysql:host=" . $host, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. Create Database if it doesn't exist
    echo "Creating database '$dbName' if it doesn't exist...\n";
    $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
    $conn->exec("USE `$dbName`");
    
    // 3. Read the schema.sql file
    $schemaPath = __DIR__ . '/schema.sql';
    if (!file_exists($schemaPath)) {
        die("Error: schema.sql not found at $schemaPath\n");
    }
    
    echo "Reading schema.sql file...\n";
    $sql = file_get_contents($schemaPath);
    
    // 4. Execute the SQL queries
    echo "Executing SQL queries. This might take a moment...\n";
    $conn->exec($sql);
    
    echo "\n✅ Migration complete! Database and tables created successfully.\n";
    
} catch(PDOException $e) {
    die("\n❌ Database connection failed: " . $e->getMessage() . "\n");
}
