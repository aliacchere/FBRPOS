# DPS POS FBR Integrated - License System Documentation

## 📋 Table of Contents
1. [Overview](#overview)
2. [Testing Phase](#testing-phase)
3. [Production Setup](#production-setup)
4. [License Server Implementation](#license-server-implementation)
5. [Client Integration](#client-integration)
6. [Security Considerations](#security-considerations)
7. [Troubleshooting](#troubleshooting)
8. [API Reference](#api-reference)

## 🎯 Overview

The DPS POS FBR Integrated license system is designed to protect your intellectual property while providing a seamless experience for legitimate users. The system supports both testing and production environments with different validation mechanisms.

### Key Features
- **Dual Environment Support** - Testing and Production modes
- **Flexible License Types** - Commercial, Standard, Trial, and Custom
- **Feature-based Licensing** - Control access to specific features
- **Expiration Management** - Automatic license expiration handling
- **Offline Validation** - Grace period for offline installations
- **Security** - Encrypted license keys and secure validation

## 🧪 Testing Phase

### Current Testing Setup

The system currently includes built-in demo licenses for immediate testing without requiring a license server.

#### Available Demo Licenses

| License Key | Type | Expires | Max Tenants | Features |
|-------------|------|---------|-------------|----------|
| `DEMO-1234-ABCD-EFGH` | Commercial | 2025-12-31 | Unlimited | All Features |
| `TEST-5678-WXYZ-1234` | Standard | 2024-12-31 | 5 | Basic Features |
| `TRIAL-9999-XXXX-XXXX` | Trial | 2024-03-31 | 1 | Limited Features |

#### Testing Without License Server

1. **Start the Installer:**
   ```bash
   # Navigate to your installation directory
   cd /path/to/dpspos-fbr/install
   
   # Start a local server (if not using web server)
   php -S localhost:8000
   ```

2. **Access the Installer:**
   - Open browser: `http://localhost:8000`
   - Complete steps 1-3 (Requirements, Database, Admin)
   - In License Verification step, use: `DEMO-1234-ABCD-EFGH`

3. **Verify License:**
   - Click "Verify License"
   - Should show "License Verified!" with full feature access
   - Continue with installation

#### Adding Custom Test Licenses

To add your own test licenses, edit `/install/verify_license.php`:

```php
$validLicenses = [
    'DEMO-1234-ABCD-EFGH' => [
        'expires_at' => '2025-12-31',
        'max_tenants' => 'Unlimited',
        'features' => ['fbr_integration', 'multi_tenant', 'pos_system', 'inventory', 'hrm', 'reporting'],
        'license_type' => 'commercial'
    ],
    // Add your custom test license
    'CUSTOM-2024-TEST-001' => [
        'expires_at' => '2024-12-31',
        'max_tenants' => 3,
        'features' => ['pos_system', 'inventory'],
        'license_type' => 'standard'
    ]
];
```

### Testing Different Scenarios

#### 1. Valid License Test
```bash
# Test with valid demo license
curl -X POST http://localhost:8000/verify_license.php \
  -H "Content-Type: application/json" \
  -d '{"key": "DEMO-1234-ABCD-EFGH"}'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "License verified successfully",
  "details": {
    "expires_at": "2025-12-31",
    "max_tenants": "Unlimited",
    "features": ["fbr_integration", "multi_tenant", "pos_system", "inventory", "hrm", "reporting"],
    "license_type": "Commercial"
  }
}
```

#### 2. Invalid Format Test
```bash
# Test with invalid format
curl -X POST http://localhost:8000/verify_license.php \
  -H "Content-Type: application/json" \
  -d '{"key": "INVALID-KEY"}'
```

**Expected Response:**
```json
{
  "success": false,
  "message": "Invalid license key format. Expected format: XXXX-XXXX-XXXX-XXXX"
}
```

#### 3. Expired License Test
```bash
# Test with expired license
curl -X POST http://localhost:8000/verify_license.php \
  -H "Content-Type: application/json" \
  -d '{"key": "TEST-5678-WXYZ-1234"}'
```

## 🚀 Production Setup

### License Server Architecture

For production, you'll need to set up a license server to manage and validate licenses. Here's the recommended architecture:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Client App    │───▶│  License Server │───▶│   Database      │
│  (DPS POS FBR)  │    │   (Your API)    │    │  (Licenses)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### 1. License Server Implementation

Create a license server using your preferred technology (PHP, Node.js, Python, etc.). Here's a PHP example:

#### License Server API (`license-server/api/verify.php`)

```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Database configuration
$db_config = [
    'host' => 'localhost',
    'dbname' => 'license_server',
    'username' => 'license_user',
    'password' => 'secure_password'
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['username'],
        $db_config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$licenseKey = $input['license_key'] ?? '';
$clientInfo = $input['client_info'] ?? [];

// Validate license key format
if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $licenseKey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid license key format'
    ]);
    exit;
}

// Check license in database
$stmt = $pdo->prepare("
    SELECT l.*, lt.name as license_type_name, lt.max_tenants, lt.features
    FROM licenses l
    JOIN license_types lt ON l.license_type_id = lt.id
    WHERE l.license_key = ? AND l.is_active = 1
");

$stmt->execute([$licenseKey]);
$license = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$license) {
    echo json_encode([
        'success' => false,
        'message' => 'License key not found or inactive'
    ]);
    exit;
}

// Check expiration
$expiresAt = new DateTime($license['expires_at']);
$now = new DateTime();

if ($expiresAt < $now) {
    echo json_encode([
        'success' => false,
        'message' => 'License has expired on ' . $license['expires_at'],
        'details' => [
            'expires_at' => $license['expires_at'],
            'license_type' => $license['license_type_name']
        ]
    ]);
    exit;
}

// Log validation attempt
$stmt = $pdo->prepare("
    INSERT INTO license_validations (license_id, client_ip, client_info, validated_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([
    $license['id'],
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    json_encode($clientInfo)
]);

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'License verified successfully',
    'details' => [
        'license_id' => $license['id'],
        'license_type' => $license['license_type_name'],
        'expires_at' => $license['expires_at'],
        'max_tenants' => $license['max_tenants'],
        'features' => json_decode($license['features'], true),
        'customer_name' => $license['customer_name'],
        'customer_email' => $license['customer_email']
    ]
]);
?>
```

#### License Server Database Schema

```sql
-- License Types Table
CREATE TABLE license_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    max_tenants INT NULL,
    features JSON NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Licenses Table
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(19) UNIQUE NOT NULL,
    license_type_id INT NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    expires_at DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (license_type_id) REFERENCES license_types(id)
);

-- License Validations Table (for tracking)
CREATE TABLE license_validations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    client_ip VARCHAR(45),
    client_info JSON,
    validated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id)
);

-- Insert default license types
INSERT INTO license_types (name, max_tenants, features, price) VALUES
('Trial', 1, '["pos_system", "basic_inventory"]', 0.00),
('Standard', 5, '["pos_system", "inventory", "basic_reporting"]', 99.00),
('Professional', 25, '["pos_system", "inventory", "hrm", "reporting", "fbr_integration"]', 299.00),
('Enterprise', NULL, '["pos_system", "inventory", "hrm", "reporting", "fbr_integration", "multi_tenant", "api_access"]', 599.00);
```

### 2. Client-Side Integration

Update the client-side license verification to connect to your license server:

#### Updated `verify_license.php`

```php
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

// Configuration
$licenseServerUrl = 'https://your-license-server.com/api/verify';
$clientInfo = [
    'app_version' => '1.0.0',
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost'
];

// Validate with license server
$validationResult = validateWithLicenseServer($licenseKey, $clientInfo);

if ($validationResult['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'License verified successfully',
        'details' => $validationResult['details']
    ]);
} else {
    // Fallback to demo licenses for testing
    $demoResult = validateWithDemoLicenses($licenseKey);
    echo json_encode($demoResult);
}

function validateWithLicenseServer($licenseKey, $clientInfo) {
    $licenseServerUrl = 'https://your-license-server.com/api/verify';
    
    $postData = json_encode([
        'license_key' => $licenseKey,
        'client_info' => $clientInfo
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $postData,
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($licenseServerUrl, false, $context);
    
    if ($response === false) {
        return [
            'success' => false,
            'message' => 'Unable to connect to license server. Please check your internet connection.'
        ];
    }
    
    return json_decode($response, true);
}

function validateWithDemoLicenses($licenseKey) {
    // Fallback demo licenses for testing
    $validLicenses = [
        'DEMO-1234-ABCD-EFGH' => [
            'expires_at' => '2025-12-31',
            'max_tenants' => 'Unlimited',
            'features' => ['fbr_integration', 'multi_tenant', 'pos_system', 'inventory', 'hrm', 'reporting'],
            'license_type' => 'Commercial'
        ]
    ];
    
    if (isset($validLicenses[$licenseKey])) {
        $license = $validLicenses[$licenseKey];
        
        $expiresAt = new DateTime($license['expires_at']);
        $now = new DateTime();
        
        if ($expiresAt < $now) {
            return [
                'success' => false,
                'message' => 'License has expired on ' . $license['expires_at']
            ];
        }
        
        return [
            'success' => true,
            'message' => 'License verified successfully (Demo Mode)',
            'details' => $license
        ];
    }
    
    return [
        'success' => false,
        'message' => 'License key not found. Please contact support.'
    ];
}
?>
```

### 3. License Generation System

Create a license generation system for your customers:

#### License Generator (`license-server/generate.php`)

```php
<?php
// License Generation System
// This should be protected with authentication

function generateLicenseKey() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $key = '';
    
    for ($i = 0; $i < 4; $i++) {
        if ($i > 0) $key .= '-';
        for ($j = 0; $j < 4; $j++) {
            $key .= $chars[rand(0, strlen($chars) - 1)];
        }
    }
    
    return $key;
}

function createLicense($customerName, $customerEmail, $licenseTypeId, $expiresAt) {
    global $pdo;
    
    $licenseKey = generateLicenseKey();
    
    // Ensure unique license key
    while (licenseExists($licenseKey)) {
        $licenseKey = generateLicenseKey();
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO licenses (license_key, license_type_id, customer_name, customer_email, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$licenseKey, $licenseTypeId, $customerName, $customerEmail, $expiresAt]);
    
    return $licenseKey;
}

function licenseExists($licenseKey) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM licenses WHERE license_key = ?");
    $stmt->execute([$licenseKey]);
    
    return $stmt->fetch() !== false;
}

// Example usage
$licenseKey = createLicense(
    'John Doe',
    'john@example.com',
    2, // Standard license type
    '2024-12-31'
);

echo "Generated License Key: " . $licenseKey;
?>
```

## 🔒 Security Considerations

### 1. License Key Security
- **Format Validation** - Strict format checking
- **Encryption** - Consider encrypting sensitive license data
- **Rate Limiting** - Prevent brute force attacks
- **IP Tracking** - Monitor validation attempts

### 2. Server Security
- **HTTPS Only** - All license server communications over HTTPS
- **API Authentication** - Secure your license server API
- **Database Security** - Proper database access controls
- **Regular Backups** - Backup license data regularly

### 3. Client Security
- **Validation Caching** - Cache validations to reduce server load
- **Offline Grace Period** - Allow limited offline usage
- **Tamper Detection** - Detect if license files are modified

## 🛠 Troubleshooting

### Common Issues

#### 1. License Verification Fails
**Problem:** License verification always fails
**Solutions:**
- Check license key format
- Verify license server is accessible
- Check database connection
- Review error logs

#### 2. Demo Licenses Not Working
**Problem:** Demo licenses don't work in testing
**Solutions:**
- Verify license key spelling
- Check date format in expiration
- Ensure proper JSON response format

#### 3. License Server Connection Issues
**Problem:** Cannot connect to license server
**Solutions:**
- Check internet connectivity
- Verify server URL
- Check firewall settings
- Review server logs

### Debug Mode

Enable debug mode for troubleshooting:

```php
// In verify_license.php
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_log("License verification attempt: " . $licenseKey);
    error_log("Server response: " . $response);
}
```

## 📚 API Reference

### License Verification API

#### Endpoint
```
POST /api/verify
```

#### Request Body
```json
{
  "license_key": "DEMO-1234-ABCD-EFGH",
  "client_info": {
    "app_version": "1.0.0",
    "php_version": "8.0.0",
    "server_software": "Apache/2.4.0",
    "domain": "example.com"
  }
}
```

#### Success Response
```json
{
  "success": true,
  "message": "License verified successfully",
  "details": {
    "license_id": 123,
    "license_type": "Commercial",
    "expires_at": "2025-12-31",
    "max_tenants": "Unlimited",
    "features": ["fbr_integration", "multi_tenant", "pos_system"],
    "customer_name": "John Doe",
    "customer_email": "john@example.com"
  }
}
```

#### Error Response
```json
{
  "success": false,
  "message": "License has expired on 2024-12-31",
  "details": {
    "expires_at": "2024-12-31",
    "license_type": "Standard"
  }
}
```

## 🚀 Getting Started with Sales

### 1. Set Up License Server
1. Deploy license server to your domain
2. Set up database with license tables
3. Configure SSL certificate
4. Test license generation and validation

### 2. Update Client Code
1. Update `verify_license.php` with your license server URL
2. Test with generated licenses
3. Deploy to production

### 3. Create Customer Portal
1. Build customer portal for license management
2. Implement license generation system
3. Set up payment processing
4. Create license delivery system

### 4. Marketing and Sales
1. Create sales pages with license purchase options
2. Set up customer support system
3. Implement license renewal system
4. Monitor license usage and compliance

## 📞 Support

For technical support with the license system:
- **Email:** support@dpspos.com
- **Documentation:** https://docs.dpspos.com/license-system
- **API Reference:** https://api.dpspos.com/license

---

This documentation provides everything you need to implement, test, and deploy the license system for your DPS POS FBR Integrated software. The system is designed to be flexible, secure, and easy to maintain while providing a smooth experience for your customers.