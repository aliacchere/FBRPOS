<?php
/**
 * DPS POS FBR Integrated - Requirements Checker
 */

header('Content-Type: application/json');

$requirements = [];

// PHP Version Check
$phpVersion = PHP_VERSION;
$phpRequired = '7.4.0';
$requirements[] = [
    'name' => 'PHP Version',
    'current' => $phpVersion,
    'required' => $phpRequired . '+',
    'status' => version_compare($phpVersion, $phpRequired, '>=')
];

// MySQL Extension Check
$requirements[] = [
    'name' => 'MySQL Extension',
    'current' => extension_loaded('mysql') || extension_loaded('mysqli') ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => extension_loaded('mysql') || extension_loaded('mysqli')
];

// PDO Extension Check
$requirements[] = [
    'name' => 'PDO Extension',
    'current' => extension_loaded('pdo') ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => extension_loaded('pdo')
];

// cURL Extension Check
$requirements[] = [
    'name' => 'cURL Extension',
    'current' => extension_loaded('curl') ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => extension_loaded('curl')
];

// GD Extension Check
$requirements[] = [
    'name' => 'GD Extension',
    'current' => extension_loaded('gd') ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => extension_loaded('gd')
];

// MBString Extension Check
$requirements[] = [
    'name' => 'MBString Extension',
    'current' => extension_loaded('mbstring') ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => extension_loaded('mbstring')
];

// OpenSSL Extension Check
$requirements[] = [
    'name' => 'OpenSSL Extension',
    'current' => extension_loaded('openssl') ? 'Available' : 'Not Available',
    'required' => 'Required',
    'status' => extension_loaded('openssl')
];

// File Permissions Check
$uploadDir = dirname(__DIR__) . '/uploads';
$configDir = dirname(__DIR__) . '/config';
$storageDir = dirname(__DIR__) . '/storage';

$directories = [$uploadDir, $configDir, $storageDir];
$writable = true;

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $writable = false;
            break;
        }
    }
    if (!is_writable($dir)) {
        $writable = false;
        break;
    }
}

$requirements[] = [
    'name' => 'File Permissions',
    'current' => $writable ? 'Writable' : 'Not Writable',
    'required' => 'Writable',
    'status' => $writable
];

// Memory Limit Check
$memoryLimit = ini_get('memory_limit');
$memoryBytes = self::convertToBytes($memoryLimit);
$requiredBytes = 128 * 1024 * 1024; // 128MB

$requirements[] = [
    'name' => 'Memory Limit',
    'current' => $memoryLimit,
    'required' => '128M+',
    'status' => $memoryBytes >= $requiredBytes
];

// Max Execution Time Check
$maxExecutionTime = ini_get('max_execution_time');
$requiredTime = 300; // 5 minutes

$requirements[] = [
    'name' => 'Max Execution Time',
    'current' => $maxExecutionTime == 0 ? 'Unlimited' : $maxExecutionTime . 's',
    'required' => '300s+',
    'status' => $maxExecutionTime == 0 || $maxExecutionTime >= $requiredTime
];

// Additional checks
$additionalChecks = [];

// Check if we can create files
$testFile = dirname(__DIR__) . '/test_write.tmp';
if (file_put_contents($testFile, 'test') !== false) {
    unlink($testFile);
    $additionalChecks['file_creation'] = true;
} else {
    $additionalChecks['file_creation'] = false;
}

// Check if we can connect to external APIs (for FBR integration)
$additionalChecks['external_api'] = function_exists('curl_init');

// Check if we have enough disk space (at least 100MB)
$freeSpace = disk_free_space(dirname(__DIR__));
$requiredSpace = 100 * 1024 * 1024; // 100MB
$additionalChecks['disk_space'] = $freeSpace >= $requiredSpace;

echo json_encode([
    'success' => true,
    'requirements' => $requirements,
    'additional_checks' => $additionalChecks,
    'overall_status' => array_reduce($requirements, function($carry, $req) {
        return $carry && $req['status'];
    }, true)
]);

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
?>