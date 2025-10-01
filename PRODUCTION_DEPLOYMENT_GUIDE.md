# DPS POS FBR Integrated - Production Deployment Guide

## 🚀 Production Setup Overview

This guide will help you deploy DPS POS FBR Integrated to production and set up a complete license management system for selling your PHP script.

## 📋 Prerequisites

### Server Requirements
- **PHP:** 7.4+ (8.0+ recommended)
- **MySQL:** 5.7+ (8.0+ recommended)
- **Web Server:** Apache 2.4+ or Nginx 1.18+
- **SSL Certificate:** Required for license server
- **Domain:** For license server and client downloads

### Recommended Hosting
- **VPS/Dedicated Server:** Full control over environment
- **Cloud Hosting:** AWS, DigitalOcean, Linode, etc.
- **Shared Hosting:** May work but limited control

## 🏗️ Step 1: License Server Setup

### 1.1 Create License Server Domain
```bash
# Example: license.yourdomain.com
# This will host your license management system
```

### 1.2 Set Up License Server Database
```sql
-- Create database
CREATE DATABASE license_server CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'license_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON license_server.* TO 'license_user'@'localhost';
FLUSH PRIVILEGES;

-- Use database
USE license_server;

-- Create tables
CREATE TABLE license_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    max_tenants INT NULL,
    features JSON NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE license_validations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    client_ip VARCHAR(45),
    client_info JSON,
    validated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id)
);

-- Insert license types
INSERT INTO license_types (name, max_tenants, features, price) VALUES
('Trial', 1, '["pos_system", "basic_inventory"]', 0.00),
('Standard', 5, '["pos_system", "inventory", "basic_reporting"]', 99.00),
('Professional', 25, '["pos_system", "inventory", "hrm", "reporting", "fbr_integration"]', 299.00),
('Enterprise', NULL, '["pos_system", "inventory", "hrm", "reporting", "fbr_integration", "multi_tenant", "api_access"]', 599.00);
```

### 1.3 Create License Server API

Create `/license-server/api/verify.php`:
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

### 1.4 Create License Generation System

Create `/license-server/admin/generate.php`:
```php
<?php
// License Generation System
// Protect this with authentication

session_start();

// Simple authentication (replace with proper auth system)
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

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

// Handle form submission
if ($_POST) {
    $customerName = $_POST['customer_name'];
    $customerEmail = $_POST['customer_email'];
    $licenseTypeId = $_POST['license_type_id'];
    $expiresAt = $_POST['expires_at'];
    
    $licenseKey = createLicense($customerName, $customerEmail, $licenseTypeId, $expiresAt);
    
    echo "<div class='success'>License generated: <strong>$licenseKey</strong></div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>License Generator</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>License Generator</h1>
    
    <form method="POST">
        <div class="form-group">
            <label>Customer Name:</label>
            <input type="text" name="customer_name" required>
        </div>
        
        <div class="form-group">
            <label>Customer Email:</label>
            <input type="email" name="customer_email" required>
        </div>
        
        <div class="form-group">
            <label>License Type:</label>
            <select name="license_type_id" required>
                <option value="1">Trial (1 tenant, basic features)</option>
                <option value="2">Standard (5 tenants, standard features)</option>
                <option value="3">Professional (25 tenants, advanced features)</option>
                <option value="4">Enterprise (unlimited tenants, all features)</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Expires At:</label>
            <input type="date" name="expires_at" required>
        </div>
        
        <button type="submit">Generate License</button>
    </form>
</body>
</html>
```

## 🛠 Step 2: Update Client Code

### 2.1 Update License Verification

Update `/install/verify_license.php`:
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

// Configuration - UPDATE THIS WITH YOUR LICENSE SERVER URL
$licenseServerUrl = 'https://license.yourdomain.com/api/verify';
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
    global $licenseServerUrl;
    
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

## 🌐 Step 3: Set Up Sales Infrastructure

### 3.1 Create Sales Website

Create a simple sales page at `https://yourdomain.com`:

```html
<!DOCTYPE html>
<html>
<head>
    <title>DPS POS FBR Integrated - Premium PHP SaaS Script</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 40px 0; text-align: center; }
        .pricing { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin: 40px 0; }
        .pricing-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; }
        .price { font-size: 2.5em; font-weight: bold; color: #2c3e50; margin: 20px 0; }
        .features { list-style: none; padding: 0; }
        .features li { padding: 10px 0; border-bottom: 1px solid #eee; }
        .btn { background: #3498db; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 1.1em; cursor: pointer; text-decoration: none; display: inline-block; margin: 20px 0; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>DPS POS FBR Integrated</h1>
        <p>Premium PHP SaaS Script for Pakistani Market</p>
    </div>
    
    <div class="container">
        <h2>Choose Your License</h2>
        
        <div class="pricing">
            <div class="pricing-card">
                <h3>Trial License</h3>
                <div class="price">Free</div>
                <ul class="features">
                    <li>1 Tenant</li>
                    <li>POS System</li>
                    <li>Basic Inventory</li>
                    <li>30 Days Trial</li>
                </ul>
                <a href="mailto:sales@yourdomain.com?subject=Trial License Request" class="btn">Request Trial</a>
            </div>
            
            <div class="pricing-card">
                <h3>Standard License</h3>
                <div class="price">$99</div>
                <ul class="features">
                    <li>5 Tenants</li>
                    <li>POS System</li>
                    <li>Inventory Management</li>
                    <li>Basic Reporting</li>
                    <li>1 Year Support</li>
                </ul>
                <a href="mailto:sales@yourdomain.com?subject=Standard License Purchase" class="btn">Buy Now</a>
            </div>
            
            <div class="pricing-card">
                <h3>Professional License</h3>
                <div class="price">$299</div>
                <ul class="features">
                    <li>25 Tenants</li>
                    <li>All Standard Features</li>
                    <li>HRM & Payroll</li>
                    <li>Advanced Reporting</li>
                    <li>FBR Integration</li>
                    <li>1 Year Support</li>
                </ul>
                <a href="mailto:sales@yourdomain.com?subject=Professional License Purchase" class="btn">Buy Now</a>
            </div>
            
            <div class="pricing-card">
                <h3>Enterprise License</h3>
                <div class="price">$599</div>
                <ul class="features">
                    <li>Unlimited Tenants</li>
                    <li>All Features</li>
                    <li>Multi-tenant Architecture</li>
                    <li>API Access</li>
                    <li>Priority Support</li>
                    <li>Custom Development</li>
                </ul>
                <a href="mailto:sales@yourdomain.com?subject=Enterprise License Purchase" class="btn">Contact Sales</a>
            </div>
        </div>
        
        <div style="text-align: center; margin: 40px 0;">
            <h3>Ready to Get Started?</h3>
            <p>Download the software and start your free trial today!</p>
            <a href="download.php" class="btn">Download Now</a>
        </div>
    </div>
</body>
</html>
```

### 3.2 Create Download Page

Create `/download.php`:
```php
<?php
// Download page with license key generation
session_start();

if ($_POST) {
    $customerName = $_POST['customer_name'];
    $customerEmail = $_POST['customer_email'];
    $licenseType = $_POST['license_type'];
    
    // Generate license key
    $licenseKey = generateLicenseKey();
    
    // Send email with download link and license key
    $subject = "DPS POS FBR Integrated - Download & License Key";
    $message = "
    Dear $customerName,
    
    Thank you for your interest in DPS POS FBR Integrated!
    
    Your License Key: $licenseKey
    
    Download Link: https://yourdomain.com/files/dpspos-fbr-v1.0.zip
    
    Installation Instructions:
    1. Download and extract the files
    2. Upload to your web server
    3. Navigate to /install in your browser
    4. Use your license key during installation
    
    If you have any questions, please contact us at support@yourdomain.com
    
    Best regards,
    DPS POS Team
    ";
    
    mail($customerEmail, $subject, $message);
    
    echo "<div style='text-align: center; padding: 40px;'>
            <h2>Thank You!</h2>
            <p>Your license key has been sent to $customerEmail</p>
            <p>License Key: <strong>$licenseKey</strong></p>
            <a href='files/dpspos-fbr-v1.0.zip' class='btn'>Download Software</a>
          </div>";
    exit;
}

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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Download DPS POS FBR Integrated</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #3498db; color: white; padding: 15px 30px; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
    </style>
</head>
<body>
    <h1>Download DPS POS FBR Integrated</h1>
    
    <form method="POST">
        <div class="form-group">
            <label>Full Name:</label>
            <input type="text" name="customer_name" required>
        </div>
        
        <div class="form-group">
            <label>Email Address:</label>
            <input type="email" name="customer_email" required>
        </div>
        
        <div class="form-group">
            <label>License Type:</label>
            <select name="license_type" required>
                <option value="trial">Trial License (Free)</option>
                <option value="standard">Standard License ($99)</option>
                <option value="professional">Professional License ($299)</option>
                <option value="enterprise">Enterprise License ($599)</option>
            </select>
        </div>
        
        <button type="submit">Get Download Link & License Key</button>
    </form>
</body>
</html>
```

## 🔒 Step 4: Security Configuration

### 4.1 SSL Certificate
```bash
# Install Let's Encrypt SSL
sudo apt install certbot
sudo certbot --apache -d license.yourdomain.com
sudo certbot --apache -d yourdomain.com
```

### 4.2 Firewall Configuration
```bash
# Configure UFW
sudo ufw allow 22
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

### 4.3 Database Security
```sql
-- Remove default users
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Flush privileges
FLUSH PRIVILEGES;
```

## 📊 Step 5: Monitoring & Analytics

### 5.1 License Usage Tracking
```php
// Add to license server API
function trackLicenseUsage($licenseId, $action, $details) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO license_usage (license_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([$licenseId, $action, json_encode($details)]);
}
```

### 5.2 Analytics Dashboard
Create a simple dashboard to track:
- License validations
- Customer downloads
- Revenue tracking
- Support requests

## 🚀 Step 6: Go Live Checklist

### Pre-Launch
- [ ] License server deployed and tested
- [ ] SSL certificates installed
- [ ] Database backups configured
- [ ] Client code updated with production license server URL
- [ ] Sales website live
- [ ] Download system working
- [ ] Email notifications working
- [ ] Support system in place

### Launch Day
- [ ] Monitor license server logs
- [ ] Check email delivery
- [ ] Test customer downloads
- [ ] Verify license generation
- [ ] Monitor server performance

### Post-Launch
- [ ] Customer support ready
- [ ] Regular backups scheduled
- [ ] Performance monitoring active
- [ ] Security updates planned
- [ ] Feature updates planned

## 📞 Support & Maintenance

### Customer Support
- **Email:** support@yourdomain.com
- **Documentation:** https://docs.yourdomain.com
- **Video Tutorials:** https://youtube.com/yourchannel

### Maintenance Tasks
- **Daily:** Check server logs and performance
- **Weekly:** Review license usage and customer feedback
- **Monthly:** Update security patches and features
- **Quarterly:** Review pricing and business metrics

---

This production deployment guide will help you set up a complete license management system for selling your DPS POS FBR Integrated software. Follow each step carefully and test thoroughly before going live.