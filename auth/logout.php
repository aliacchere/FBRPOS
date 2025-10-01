<?php
/**
 * DPS POS FBR Integrated - Logout
 */

session_start();

// Include configuration
require_once '../config/database.php';
require_once '../includes/functions.php';

// Log the logout if user is logged in
if (is_logged_in()) {
    db_insert('audit_logs', [
        'tenant_id' => $_SESSION['tenant_id'] ?? null,
        'user_id' => $_SESSION['user_id'],
        'action' => 'logout',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

// Clear remember me token if exists
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    db_delete('remember_tokens', 'token = ?', [hash('sha256', $token)]);
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: ../login.php?success=You have been logged out successfully');
exit;
?>