<?php
/**
 * DPS POS FBR Integrated - Database Configuration
 */

// Database Configuration
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'dpspos_fbr',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

// Create PDO connection
try {
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['database']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Database helper functions
function db_query($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        return false;
    }
}

function db_fetch($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

function db_fetch_all($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

function db_insert($table, $data) {
    global $pdo;
    $columns = implode(',', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    
    $stmt = db_query($sql, $data);
    return $stmt ? $pdo->lastInsertId() : false;
}

function db_update($table, $data, $where, $where_params = []) {
    $set_clause = [];
    foreach ($data as $key => $value) {
        $set_clause[] = "{$key} = :{$key}";
    }
    $set_clause = implode(', ', $set_clause);
    
    $sql = "UPDATE {$table} SET {$set_clause} WHERE {$where}";
    $params = array_merge($data, $where_params);
    
    return db_query($sql, $params);
}

function db_delete($table, $where, $params = []) {
    $sql = "DELETE FROM {$table} WHERE {$where}";
    return db_query($sql, $params);
}
?>