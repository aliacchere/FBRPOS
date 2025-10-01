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

if (!$input || !isset($input['key'])) {
    echo json_encode(['success' => false, 'message' => 'License key is required']);
    exit;
}

$licenseKey = trim($input['key']);

// Validate license key format
if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $licenseKey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid license key format. Expected format: XXXX-XXXX-XXXX-XXXX'
    ]);
    exit;
}

// For demo purposes, we'll simulate license verification
// In production, this would connect to your license server
$validLicenses = [
    'DEMO-1234-ABCD-EFGH' => [
        'expires_at' => '2025-12-31',
        'max_tenants' => 'Unlimited',
        'features' => ['fbr_integration', 'multi_tenant', 'pos_system', 'inventory', 'hrm', 'reporting']
    ],
    'TEST-5678-WXYZ-1234' => [
        'expires_at' => '2024-12-31',
        'max_tenants' => 5,
        'features' => ['fbr_integration', 'multi_tenant', 'pos_system']
    ]
];

if (isset($validLicenses[$licenseKey])) {
    $license = $validLicenses[$licenseKey];
    
    // Check if license is expired
    $expiresAt = new DateTime($license['expires_at']);
    $now = new DateTime();
    
    if ($expiresAt < $now) {
        echo json_encode([
            'success' => false,
            'message' => 'License has expired on ' . $license['expires_at'],
            'details' => $license
        ]);
        exit;
    }
    
    // License is valid
    echo json_encode([
        'success' => true,
        'message' => 'License verified successfully',
        'details' => [
            'expires_at' => $license['expires_at'],
            'max_tenants' => $license['max_tenants'],
            'features' => $license['features'],
            'license_type' => $license['max_tenants'] === 'Unlimited' ? 'Commercial' : 'Standard'
        ]
    ]);
} else {
    // Try to validate with external license server (simulated)
    $validationResult = validateWithLicenseServer($licenseKey);
    
    if ($validationResult['valid']) {
        echo json_encode([
            'success' => true,
            'message' => 'License verified successfully',
            'details' => $validationResult['details']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $validationResult['message'] ?? 'Invalid license key',
            'details' => $validationResult['details'] ?? null
        ]);
    }
}

function validateWithLicenseServer($licenseKey) {
    // Simulate external license server validation
    // In production, this would make an HTTP request to your license server
    
    // For demo purposes, we'll simulate some validation logic
    $hash = hash('sha256', $licenseKey . 'DPS_POS_FBR_SECRET_KEY');
    
    // Check if it's a valid hash pattern (simplified validation)
    if (strlen($hash) === 64 && ctype_xdigit($hash)) {
        return [
            'valid' => true,
            'details' => [
                'expires_at' => '2025-12-31',
                'max_tenants' => 'Unlimited',
                'features' => ['fbr_integration', 'multi_tenant', 'pos_system', 'inventory', 'hrm', 'reporting', 'template_editor', 'data_import_export'],
                'license_type' => 'Commercial'
            ]
        ];
    }
    
    return [
        'valid' => false,
        'message' => 'License key not found in our system. Please contact support.',
        'details' => null
    ];
}
?>