<?php
/**
 * DPS POS FBR Integrated - Database Connection Tester
 */

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

try {
    // Test database connection
    $dsn = "mysql:host={$data['host']};charset=utf8mb4";
    $pdo = new PDO($dsn, $data['username'], $data['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Test if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$data['name']}'");
    $databaseExists = $stmt->rowCount() > 0;

    if (!$databaseExists) {
        // Try to create database
        $pdo->exec("CREATE DATABASE `{$data['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    // Test connection to the specific database
    $dsn = "mysql:host={$data['host']};dbname={$data['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $data['username'], $data['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Test if we can create tables
    $testTable = 'test_' . uniqid();
    $pdo->exec("CREATE TABLE `{$testTable}` (id INT PRIMARY KEY)");
    $pdo->exec("DROP TABLE `{$testTable}`");

    // Test MySQL version
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch()['version'];
    $versionOk = version_compare($version, '5.7.0', '>=');

    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful!',
        'database_created' => !$databaseExists,
        'mysql_version' => $version,
        'version_ok' => $versionOk
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage()
    ]);
}
?>