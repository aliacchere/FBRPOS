<?php
/**
 * DPS POS FBR Integrated - Login Authentication
 */

session_start();

// Include configuration
require_once '../config/database.php';
require_once '../config/app.php';
require_once '../includes/functions.php';

// Check if already logged in
if (is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// Get and sanitize input
$email = sanitize_input($_POST['email']);
$password = $_POST['password'];
$remember = isset($_POST['remember']);

// Validate input
if (empty($email) || empty($password)) {
    header('Location: ../login.php?error=Please fill in all fields');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../login.php?error=Please enter a valid email address');
    exit;
}

// Check if user exists
$user = db_fetch("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);

if (!$user) {
    header('Location: ../login.php?error=Invalid email or password');
    exit;
}

// Verify password
if (!password_verify($password, $user['password'])) {
    header('Location: ../login.php?error=Invalid email or password');
    exit;
}

// Get tenant information if user is not super admin
$tenant = null;
if ($user['role'] !== 'super_admin' && $user['tenant_id']) {
    $tenant = db_fetch("SELECT * FROM tenants WHERE id = ? AND is_active = 1", [$user['tenant_id']]);
    
    if (!$tenant) {
        header('Location: ../login.php?error=Your account is not associated with an active business');
        exit;
    }
}

// Update last login
db_update('users', 
    ['last_login' => date('Y-m-d H:i:s')], 
    'id = ?', 
    [$user['id']]
);

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['tenant_id'] = $user['tenant_id'];

if ($tenant) {
    $_SESSION['tenant_name'] = $tenant['business_name'];
    $_SESSION['tenant_ntn'] = $tenant['ntn'];
}

// Set remember me cookie if requested
if ($remember) {
    $token = bin2hex(random_bytes(32));
    $expires = time() + (30 * 24 * 60 * 60); // 30 days
    
    setcookie('remember_token', $token, $expires, '/', '', false, true);
    
    // Store token in database
    db_insert('remember_tokens', [
        'user_id' => $user['id'],
        'token' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', $expires),
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

// Log the login
db_insert('audit_logs', [
    'tenant_id' => $user['tenant_id'],
    'user_id' => $user['id'],
    'action' => 'login',
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'created_at' => date('Y-m-d H:i:s')
]);

// Redirect based on user role
switch ($user['role']) {
    case 'super_admin':
        header('Location: ../admin/dashboard.php');
        break;
    case 'tenant_admin':
        header('Location: ../tenant/dashboard.php');
        break;
    case 'cashier':
        header('Location: ../pos/sales.php');
        break;
    default:
        header('Location: ../login.php?error=Invalid user role');
        break;
}
?>