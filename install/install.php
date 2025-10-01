<?php
/**
 * DPS POS FBR Integrated - Installation Script
 */

// Set content type to JSON
header('Content-Type: application/json');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

try {
    // Step 1: Test database connection
    $db_config = [
        'host' => $data['db_host'],
        'username' => $data['db_username'],
        'password' => $data['db_password'],
        'database' => $data['db_name']
    ];

    $dsn = "mysql:host={$db_config['host']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db_config['database']}`");

    // Step 2: Create database tables
    $sql_file = __DIR__ . '/database.sql';
    if (!file_exists($sql_file)) {
        throw new Exception('Database schema file not found');
    }

    $sql = file_get_contents($sql_file);
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }

    // Step 3: Create configuration files
    $config_dir = dirname(__DIR__) . '/config';
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    // Create database.php
    $db_config_content = "<?php\n";
    $db_config_content .= "/**\n";
    $db_config_content .= " * DPS POS FBR Integrated - Database Configuration\n";
    $db_config_content .= " * Generated during installation\n";
    $db_config_content .= " */\n\n";
    $db_config_content .= "// Database Configuration\n";
    $db_config_content .= "\$db_config = [\n";
    $db_config_content .= "    'host' => '{$data['db_host']}',\n";
    $db_config_content .= "    'username' => '{$data['db_username']}',\n";
    $db_config_content .= "    'password' => '{$data['db_password']}',\n";
    $db_config_content .= "    'database' => '{$data['db_name']}',\n";
    $db_config_content .= "    'charset' => 'utf8mb4',\n";
    $db_config_content .= "    'collation' => 'utf8mb4_unicode_ci'\n";
    $db_config_content .= "];\n\n";
    $db_config_content .= "// Create PDO connection\n";
    $db_config_content .= "try {\n";
    $db_config_content .= "    \$dsn = \"mysql:host={\$db_config['host']};dbname={\$db_config['database']};charset={\$db_config['charset']}\";\n";
    $db_config_content .= "    \$pdo = new PDO(\$dsn, \$db_config['username'], \$db_config['password'], [\n";
    $db_config_content .= "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
    $db_config_content .= "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
    $db_config_content .= "        PDO::ATTR_EMULATE_PREPARES => false,\n";
    $db_config_content .= "    ]);\n";
    $db_config_content .= "} catch (PDOException \$e) {\n";
    $db_config_content .= "    die(\"Database connection failed: \" . \$e->getMessage());\n";
    $db_config_content .= "}\n\n";
    $db_config_content .= "// Database helper functions\n";
    $db_config_content .= "function db_query(\$sql, \$params = []) {\n";
    $db_config_content .= "    global \$pdo;\n";
    $db_config_content .= "    try {\n";
    $db_config_content .= "        \$stmt = \$pdo->prepare(\$sql);\n";
    $db_config_content .= "        \$stmt->execute(\$params);\n";
    $db_config_content .= "        return \$stmt;\n";
    $db_config_content .= "    } catch (PDOException \$e) {\n";
    $db_config_content .= "        error_log(\"Database query error: \" . \$e->getMessage());\n";
    $db_config_content .= "        return false;\n";
    $db_config_content .= "    }\n";
    $db_config_content .= "}\n\n";
    $db_config_content .= "function db_fetch(\$sql, \$params = []) {\n";
    $db_config_content .= "    \$stmt = db_query(\$sql, \$params);\n";
    $db_config_content .= "    return \$stmt ? \$stmt->fetch() : false;\n";
    $db_config_content .= "}\n\n";
    $db_config_content .= "function db_fetch_all(\$sql, \$params = []) {\n";
    $db_config_content .= "    \$stmt = db_query(\$sql, \$params);\n";
    $db_config_content .= "    return \$stmt ? \$stmt->fetchAll() : false;\n";
    $db_config_content .= "}\n\n";
    $db_config_content .= "function db_insert(\$table, \$data) {\n";
    $db_config_content .= "    global \$pdo;\n";
    $db_config_content .= "    \$columns = implode(',', array_keys(\$data));\n";
    $db_config_content .= "    \$placeholders = ':' . implode(', :', array_keys(\$data));\n";
    $db_config_content .= "    \$sql = \"INSERT INTO {\$table} ({\$columns}) VALUES ({\$placeholders})\";\n";
    $db_config_content .= "    \$stmt = db_query(\$sql, \$data);\n";
    $db_config_content .= "    return \$stmt ? \$pdo->lastInsertId() : false;\n";
    $db_config_content .= "}\n\n";
    $db_config_content .= "function db_update(\$table, \$data, \$where, \$where_params = []) {\n";
    $db_config_content .= "    \$set_clause = [];\n";
    $db_config_content .= "    foreach (\$data as \$key => \$value) {\n";
    $db_config_content .= "        \$set_clause[] = \"{\$key} = :{\$key}\";\n";
    $db_config_content .= "    }\n";
    $db_config_content .= "    \$set_clause = implode(', ', \$set_clause);\n";
    $db_config_content .= "    \$sql = \"UPDATE {\$table} SET {\$set_clause} WHERE {\$where}\";\n";
    $db_config_content .= "    \$params = array_merge(\$data, \$where_params);\n";
    $db_config_content .= "    return db_query(\$sql, \$params);\n";
    $db_config_content .= "}\n\n";
    $db_config_content .= "function db_delete(\$table, \$where, \$params = []) {\n";
    $db_config_content .= "    \$sql = \"DELETE FROM {\$table} WHERE {\$where}\";\n";
    $db_config_content .= "    return db_query(\$sql, \$params);\n";
    $db_config_content .= "}\n";

    file_put_contents($config_dir . '/database.php', $db_config_content);

    // Update app.php with installation data
    $app_config_content = file_get_contents(dirname(__DIR__) . '/config/app.php');
    $app_config_content = str_replace('http://localhost/dpspos', $data['app_url'], $app_config_content);
    $app_config_content = str_replace('your-32-character-secret-key-here', $data['encryption_key'], $app_config_content);
    $app_config_content = str_replace('', $data['whatsapp_number'], $app_config_content);
    $app_config_content = str_replace('Asia/Karachi', $data['timezone'], $app_config_content);
    
    file_put_contents($config_dir . '/app.php', $app_config_content);

    // Step 4: Create super admin user
    $admin_password = password_hash($data['admin_password'], PASSWORD_DEFAULT);
    
    $admin_data = [
        'name' => $data['admin_name'],
        'email' => $data['admin_email'],
        'password' => $admin_password,
        'role' => 'super_admin',
        'tenant_id' => null,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $admin_id = db_insert('users', $admin_data);

    if (!$admin_id) {
        throw new Exception('Failed to create super admin user');
    }

    // Step 5: Create upload directories
    $upload_dirs = [
        'uploads/',
        'uploads/tenants/',
        'uploads/products/',
        'uploads/logos/',
        'uploads/receipts/'
    ];

    foreach ($upload_dirs as $dir) {
        $full_path = dirname(__DIR__) . '/' . $dir;
        if (!is_dir($full_path)) {
            mkdir($full_path, 0755, true);
        }
    }

    // Step 6: Create .htaccess files for security
    $htaccess_content = "Options -Indexes\n";
    $htaccess_content .= "RewriteEngine On\n";
    $htaccess_content .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccess_content .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccess_content .= "RewriteRule ^(.*)$ index.php [QSA,L]\n";

    file_put_contents(dirname(__DIR__) . '/.htaccess', $htaccess_content);

    // Step 7: Create installation lock file
    $lock_content = "<?php\n";
    $lock_content .= "// DPS POS FBR Integrated - Installation Lock\n";
    $lock_content .= "// This file prevents re-installation\n";
    $lock_content .= "// Delete this file to re-run installation\n";
    $lock_content .= "define('INSTALLATION_LOCK', true);\n";

    file_put_contents($config_dir . '/install.lock', $lock_content);

    // Step 8: Create sample data
    // Insert default provinces
    $provinces = [
        ['name' => 'Punjab', 'code' => 'PUN'],
        ['name' => 'Sindh', 'code' => 'SIN'],
        ['name' => 'Khyber Pakhtunkhwa', 'code' => 'KPK'],
        ['name' => 'Balochistan', 'code' => 'BAL'],
        ['name' => 'Islamabad Capital Territory', 'code' => 'ICT'],
        ['name' => 'Azad Jammu and Kashmir', 'code' => 'AJK'],
        ['name' => 'Gilgit-Baltistan', 'code' => 'GB']
    ];

    foreach ($provinces as $province) {
        db_insert('provinces', $province);
    }

    // Insert default units of measure
    $uom = [
        ['name' => 'Numbers, pieces, units', 'code' => 'PCE'],
        ['name' => 'Kilograms', 'code' => 'KGM'],
        ['name' => 'Grams', 'code' => 'GRM'],
        ['name' => 'Liters', 'code' => 'LTR'],
        ['name' => 'Meters', 'code' => 'MTR'],
        ['name' => 'Square meters', 'code' => 'MTK'],
        ['name' => 'Cubic meters', 'code' => 'MTQ'],
        ['name' => 'Dozen', 'code' => 'DZN'],
        ['name' => 'Gross', 'code' => 'GRO'],
        ['name' => 'Box', 'code' => 'BX']
    ];

    foreach ($uom as $unit) {
        db_insert('units_of_measure', $unit);
    }

    // Insert default HS codes
    $hs_codes = [
        ['code' => '0101.2100', 'description' => 'Live horses, asses, mules and hinnies'],
        ['code' => '0102.2100', 'description' => 'Live bovine animals'],
        ['code' => '0103.2100', 'description' => 'Live swine'],
        ['code' => '0104.2100', 'description' => 'Live sheep and goats'],
        ['code' => '0105.2100', 'description' => 'Live poultry'],
        ['code' => '0201.2100', 'description' => 'Meat of bovine animals, fresh or chilled'],
        ['code' => '0202.2100', 'description' => 'Meat of bovine animals, frozen'],
        ['code' => '0203.2100', 'description' => 'Meat of swine, fresh, chilled or frozen'],
        ['code' => '0204.2100', 'description' => 'Meat of sheep or goats, fresh, chilled or frozen'],
        ['code' => '0205.2100', 'description' => 'Meat of horses, asses, mules or hinnies, fresh, chilled or frozen']
    ];

    foreach ($hs_codes as $hs_code) {
        db_insert('hs_codes', $hs_code);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Installation completed successfully!',
        'admin_id' => $admin_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>