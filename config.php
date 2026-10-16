<?php
// Invoice Pro - Aiven MySQL connection

$dbHost = getenv('DB_HOST') ?: '';
$dbPort = getenv('DB_PORT') ?: '13207';
$dbName = getenv('DB_NAME') ?: 'defaultdb';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASSWORD') ?: '';
$caFile = __DIR__ . '/ca.pem';

if (!$dbHost || !$dbUser || !$dbPass) {
    die('Database configuration is missing. Check Render environment variables.');
}   

try {
    $options = [    
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (is_file($caFile)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
    } else {
        die('Aiven CA certificate ca.pem is missing.');
    }

    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

    $db = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the database configuration.');
}
