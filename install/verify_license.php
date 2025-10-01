<?php
/**
 * DPS POS FBR Integrated - License Verification
 */

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['key'])) {
    echo json_encode([
        'success' => false,
        'message' => 'License key is required'
    ]);
    exit;
}

$licenseKey = trim($data['key']);

// Validate license key format (XXXX-XXXX-XXXX-XXXX)
if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $licenseKey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid license key format. Expected: XXXX-XXXX-XXXX-XXXX'
    ]);
    exit;
}

// License verification logic
$isValid = verifyLicenseKey($licenseKey);

if ($isValid) {
    echo json_encode([
        'success' => true,
        'message' => 'License key verified successfully!',
        'license_info' => [
            'key' => $licenseKey,
            'type' => 'Enterprise',
            'expires' => '2025-12-31',
            'features' => [
                'Multi-tenant SaaS',
                'FBR Integration',
                'Advanced Reporting',
                'WhatsApp Integration',
                'Bulk Import/Export',
                'System Backup/Restore'
            ]
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid license key. Please check your key and try again.'
    ]);
}

function verifyLicenseKey($key) {
    // In a real implementation, this would verify against a license server
    // For demo purposes, we'll accept any properly formatted key
    
    // Demo valid keys (in production, these would be verified against a server)
    $validKeys = [
        'DEMO-1234-5678-9ABC',
        'TEST-ABCD-EFGH-IJKL',
        'PROD-MNOP-QRST-UVWX'
    ];
    
    // For demo, accept any key that starts with DEMO, TEST, or PROD
    $prefix = substr($key, 0, 4);
    return in_array($prefix, ['DEMO', 'TEST', 'PROD']) || in_array($key, $validKeys);
}

// Alternative: Online license verification
function verifyLicenseOnline($key) {
    $licenseServer = 'https://license.dpspos.com/verify';
    
    $data = [
        'key' => $key,
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'product' => 'dpspos-fbr-integrated',
        'version' => '2.0.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $licenseServer);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: DPS-POS-Installer/2.0.0'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return $result['valid'] ?? false;
    }
    
    return false;
}
?>