<?php
/**
 * DPS POS FBR Integrated - Enhanced Requirements Checker
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Function to convert memory limit to bytes
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value)-1]);
    $value = (int) $value;
    
    switch($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    
    return $value;
}

// Function to check if directory is writable
function isDirectoryWritable($dir) {
    if (!is_dir($dir)) {
        return @mkdir($dir, 0755, true) && is_writable($dir);
    }
    return is_writable($dir);
}

$requirements = [];
$allPassed = true;

// PHP Version Check (7.4+)
$phpVersion = PHP_VERSION;
$phpRequired = '7.4.0';
$phpStatus = version_compare($phpVersion, $phpRequired, '>=');
$allPassed = $allPassed && $phpStatus;

$requirements[] = [
    'name' => 'PHP Version',
    'current' => $phpVersion,
    'required' => $phpRequired . '+',
    'status' => $phpStatus,
    'message' => $phpStatus ? 'PHP version is compatible' : 'PHP 7.4 or higher is required'
];

// MySQL Extension Check (mysqli or pdo_mysql)
$mysqlStatus = extension_loaded('mysqli') || extension_loaded('pdo_mysql');
$allPassed = $allPassed && $mysqlStatus;

$requirements[] = [
    'name' => 'MySQL Extension',
    'current' => $mysqlStatus ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => $mysqlStatus,
    'message' => $mysqlStatus ? 'MySQL extension is available' : 'MySQL extension (mysqli or pdo_mysql) is required'
];

// PDO Extension Check
$pdoStatus = extension_loaded('pdo');
$allPassed = $allPassed && $pdoStatus;

$requirements[] = [
    'name' => 'PDO Extension',
    'current' => $pdoStatus ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => $pdoStatus,
    'message' => $pdoStatus ? 'PDO extension is available' : 'PDO extension is required'
];

// cURL Extension Check
$curlStatus = extension_loaded('curl');
$allPassed = $allPassed && $curlStatus;

$requirements[] = [
    'name' => 'cURL Extension',
    'current' => $curlStatus ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => $curlStatus,
    'message' => $curlStatus ? 'cURL extension is available' : 'cURL extension is required for FBR integration'
];

// GD Extension Check
$gdStatus = extension_loaded('gd');
$allPassed = $allPassed && $gdStatus;

$requirements[] = [
    'name' => 'GD Extension',
    'current' => $gdStatus ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => $gdStatus,
    'message' => $gdStatus ? 'GD extension is available' : 'GD extension is required for image processing'
];

// MBString Extension Check
$mbstringStatus = extension_loaded('mbstring');
$allPassed = $allPassed && $mbstringStatus;

$requirements[] = [
    'name' => 'MBString Extension',
    'current' => $mbstringStatus ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => $mbstringStatus,
    'message' => $mbstringStatus ? 'MBString extension is available' : 'MBString extension is required for string handling'
];

// OpenSSL Extension Check
$opensslStatus = extension_loaded('openssl');
$allPassed = $allPassed && $opensslStatus;

$requirements[] = [
    'name' => 'OpenSSL Extension',
    'current' => $opensslStatus ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => $opensslStatus,
    'message' => $opensslStatus ? 'OpenSSL extension is available' : 'OpenSSL extension is required for secure connections'
];

// File Permissions Check
$baseDir = dirname(__DIR__);
$directories = [
    $baseDir . '/storage',
    $baseDir . '/bootstrap/cache',
    $baseDir . '/public/uploads',
    $baseDir . '/config'
];

$writableStatus = true;
$writableDirs = [];
$nonWritableDirs = [];

foreach ($directories as $dir) {
    if (isDirectoryWritable($dir)) {
        $writableDirs[] = basename($dir);
    } else {
        $writableStatus = false;
        $nonWritableDirs[] = basename($dir);
    }
}

$allPassed = $allPassed && $writableStatus;

$requirements[] = [
    'name' => 'File Permissions',
    'current' => $writableStatus ? 'Writable' : 'Not Writable',
    'required' => 'Writable',
    'status' => $writableStatus,
    'message' => $writableStatus ? 'All required directories are writable' : 'Directories not writable: ' . implode(', ', $nonWritableDirs),
    'details' => [
        'writable' => $writableDirs,
        'non_writable' => $nonWritableDirs
    ]
];

// Memory Limit Check
$memoryLimit = ini_get('memory_limit');
$memoryBytes = convertToBytes($memoryLimit);
$requiredBytes = 128 * 1024 * 1024; // 128MB
$memoryStatus = $memoryBytes >= $requiredBytes;
$allPassed = $allPassed && $memoryStatus;

$requirements[] = [
    'name' => 'Memory Limit',
    'current' => $memoryLimit,
    'required' => '128M+',
    'status' => $memoryStatus,
    'message' => $memoryStatus ? 'Memory limit is sufficient' : 'Memory limit should be at least 128MB',
    'bytes' => $memoryBytes,
    'required_bytes' => $requiredBytes
];

// Max Execution Time Check
$maxExecutionTime = ini_get('max_execution_time');
$requiredTime = 300; // 5 minutes
$timeStatus = $maxExecutionTime == 0 || $maxExecutionTime >= $requiredTime;
$allPassed = $allPassed && $timeStatus;

$requirements[] = [
    'name' => 'Max Execution Time',
    'current' => $maxExecutionTime == 0 ? 'Unlimited' : $maxExecutionTime . 's',
    'required' => '300s+',
    'status' => $timeStatus,
    'message' => $timeStatus ? 'Execution time limit is sufficient' : 'Max execution time should be at least 300 seconds'
];

// Additional System Checks
$additionalChecks = [];

// Check if we can create files
$testFile = $baseDir . '/test_write_' . time() . '.tmp';
$fileCreationStatus = @file_put_contents($testFile, 'test') !== false;
if ($fileCreationStatus) {
    @unlink($testFile);
}

$additionalChecks['file_creation'] = [
    'status' => $fileCreationStatus,
    'message' => $fileCreationStatus ? 'File creation test passed' : 'Cannot create files in the directory'
];

// Check if we can connect to external APIs (for FBR integration)
$externalApiStatus = function_exists('curl_init') && function_exists('curl_exec');
$additionalChecks['external_api'] = [
    'status' => $externalApiStatus,
    'message' => $externalApiStatus ? 'External API connectivity available' : 'cURL functions not available for external API calls'
];

// Check if we have enough disk space (at least 500MB)
$freeSpace = disk_free_space($baseDir);
$requiredSpace = 500 * 1024 * 1024; // 500MB
$diskSpaceStatus = $freeSpace >= $requiredSpace;
$additionalChecks['disk_space'] = [
    'status' => $diskSpaceStatus,
    'message' => $diskSpaceStatus ? 'Sufficient disk space available' : 'Insufficient disk space (need at least 500MB)',
    'free_space' => $freeSpace,
    'required_space' => $requiredSpace
];

// Check for required PHP functions
$requiredFunctions = ['json_encode', 'json_decode', 'hash', 'openssl_encrypt', 'openssl_decrypt'];
$missingFunctions = [];
foreach ($requiredFunctions as $func) {
    if (!function_exists($func)) {
        $missingFunctions[] = $func;
    }
}

$functionsStatus = empty($missingFunctions);
$additionalChecks['php_functions'] = [
    'status' => $functionsStatus,
    'message' => $functionsStatus ? 'All required PHP functions are available' : 'Missing functions: ' . implode(', ', $missingFunctions),
    'missing' => $missingFunctions
];

// Check for required classes
$requiredClasses = ['PDO', 'DateTime', 'Exception'];
$missingClasses = [];
foreach ($requiredClasses as $class) {
    if (!class_exists($class)) {
        $missingClasses[] = $class;
    }
}

$classesStatus = empty($missingClasses);
$additionalChecks['php_classes'] = [
    'status' => $classesStatus,
    'message' => $classesStatus ? 'All required PHP classes are available' : 'Missing classes: ' . implode(', ', $missingClasses),
    'missing' => $missingClasses
];

// Overall status calculation
$overallStatus = $allPassed && 
                 $additionalChecks['file_creation']['status'] && 
                 $additionalChecks['external_api']['status'] && 
                 $additionalChecks['disk_space']['status'] &&
                 $additionalChecks['php_functions']['status'] &&
                 $additionalChecks['php_classes']['status'];

// System information
$systemInfo = [
    'php_version' => PHP_VERSION,
    'php_sapi' => PHP_SAPI,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'operating_system' => PHP_OS,
    'architecture' => php_uname('m'),
    'timezone' => date_default_timezone_get(),
    'date' => date('Y-m-d H:i:s')
];

echo json_encode([
    'success' => true,
    'overall_status' => $overallStatus,
    'requirements' => $requirements,
    'additional_checks' => $additionalChecks,
    'system_info' => $systemInfo,
    'summary' => [
        'total_checks' => count($requirements),
        'passed_checks' => count(array_filter($requirements, function($req) { return $req['status']; })),
        'failed_checks' => count(array_filter($requirements, function($req) { return !$req['status']; })),
        'can_proceed' => $overallStatus
    ]
], JSON_PRETTY_PRINT);
?>