<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$host = $input['host'] ?? 'localhost';
$name = $input['name'] ?? '';
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$port = $input['port'] ?? 3306;
$charset = $input['charset'] ?? 'utf8mb4';

if (empty($name) || empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Database name and username are required']);
    exit;
}

try {
    // Test connection
    $dsn = "mysql:host={$host};port={$port};charset={$charset}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Test if database exists, if not try to create it
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($name));
    $dbExists = $stmt->fetch();
    
    if (!$dbExists) {
        // Try to create database
        $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
    }
    
    // Test connection to the specific database
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Test basic queries
    $pdo->query("SELECT 1");
    
    // Get MySQL version
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch()['version'];
    
    // Check if user has necessary privileges
    $stmt = $pdo->query("SHOW GRANTS");
    $grants = $stmt->fetchAll();
    
    $hasCreatePrivilege = false;
    $hasInsertPrivilege = false;
    $hasUpdatePrivilege = false;
    $hasDeletePrivilege = false;
    
    foreach ($grants as $grant) {
        $grantText = $grant['Grants for ' . $username . '@' . $host] ?? '';
        if (stripos($grantText, 'CREATE') !== false) $hasCreatePrivilege = true;
        if (stripos($grantText, 'INSERT') !== false) $hasInsertPrivilege = true;
        if (stripos($grantText, 'UPDATE') !== false) $hasUpdatePrivilege = true;
        if (stripos($grantText, 'DELETE') !== false) $hasDeletePrivilege = true;
    }
    
    $privileges = [];
    if ($hasCreatePrivilege) $privileges[] = 'CREATE';
    if ($hasInsertPrivilege) $privileges[] = 'INSERT';
    if ($hasUpdatePrivilege) $privileges[] = 'UPDATE';
    if ($hasDeletePrivilege) $privileges[] = 'DELETE';
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'details' => [
            'host' => $host,
            'database' => $name,
            'username' => $username,
            'port' => $port,
            'charset' => $charset,
            'mysql_version' => $version,
            'privileges' => $privileges,
            'created_database' => !$dbExists
        ]
    ]);
    
} catch (PDOException $e) {
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();
    
    // Provide user-friendly error messages
    switch ($errorCode) {
        case 1045:
            $message = 'Access denied. Please check your username and password.';
            break;
        case 2002:
            $message = 'Cannot connect to database server. Please check the host and port.';
            break;
        case 1049:
            $message = 'Database does not exist and cannot be created. Please check your privileges.';
            break;
        case 1044:
            $message = 'Access denied. User does not have permission to access the database.';
            break;
        default:
            $message = 'Database connection failed: ' . $errorMessage;
    }
    
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error_code' => $errorCode,
        'details' => [
            'host' => $host,
            'database' => $name,
            'username' => $username,
            'port' => $port
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage()
    ]);
}
?>